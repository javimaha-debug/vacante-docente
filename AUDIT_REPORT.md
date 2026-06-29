# Doccentia — Comprehensive Security & UX Audit

**Repo:** javimaha-debug/vacante-docente · **Branch audited:** `main` (at `fad71de`)
**Date:** 2026-06-29 · **Scope:** full security audit + full UX/accessibility/performance audit
**Status:** findings only — nothing was fixed. This report is input for the next sprint.

---

## Executive summary

Overall the codebase is **solid on security fundamentals** (no committed secrets, OAuth tokens encrypted at rest, signed URLs for documents, signature-verified Stripe webhook, good ownership checks on most resources). There is **one CRITICAL issue** — an unauthenticated IDOR on the `user-lists/*` routes that leaks home addresses and saved lists — that must be fixed immediately, plus a cluster of HIGH items around an inconsistent admin-authorization path, missing AI/file rate limits, and an unvalidated cloud-import upload.

On UX, the **Oposición pages are the clear gold standard** (consistency 10/10) and the **Bolsa-mode pages feel a generation behind** — the single biggest tell is that the brand display font (Bricolage Grotesque / `font-heading`) is used in *zero* Bolsa files. The systemic UX gaps are: kanban touch targets below 44px, no optimistic UI on the core kanban interaction, "Cargando…" text instead of skeletons across the whole user app, no modal focus-trap, and raw English enum values leaking into the superadmin panel.

| Area | CRITICAL | HIGH | MEDIUM | LOW |
|---|---|---|---|---|
| Security | 1 | 4 | 6 | 5 |
| UX / A11y / Perf | — | 7 | 12 | 10+ |

---

# PART 1 — SECURITY AUDIT

## 🔴 CRITICAL (fix immediately)

### SEC-C1 — Unauthenticated IDOR on `user-lists/*` leaks home address + saved lists and allows tampering
**Files:** `routes/api.php:74–86`; `app/Http/Controllers/Api/PreferenceController.php:22–70`; `app/Http/Controllers/Api/UserListController.php:38–45`; `app/Http/Requests/UpdateUserListRequest.php:9–11`

These routes sit **outside any auth middleware** and the `{userList}` binding resolves by sequential integer PK with no ownership/token check:
```
PATCH user-lists/{userList}                          (line 75)
GET   user-lists/{userList}/preferences              (line 78)
PUT   user-lists/{userList}/preferences/bulk         (line 79)
POST  user-lists/{userList}/geocode                  (line 82)
POST  user-lists/{userList}/calculate-distances      (line 85)
```
`PreferenceController::index` returns another user's full vacancy-preference list **and their home latitude/longitude** (`PreferenceController.php:34–36`) for any id; `update`/`bulk` let anyone tamper with any list. `UpdateUserListRequest::authorize()` just `return true`.

**Impact:** any anonymous attacker can enumerate ids to read every user's saved choices + home location (personal data), and modify any list.

**Fix:** the model already has a secret `session_token` (`StoreUserListRequest.php:17`). Enforce it on every `{userList}` route (or move them behind `auth:sanctum` and check `user_id`):
```php
abort_unless(
    hash_equals((string) $userList->session_token, (string) $request->header('X-Session-Token')),
    403
);
```
and make `UpdateUserListRequest::authorize()` perform that check instead of returning `true`. Stop exposing the numeric id; bind by token.

---

## 🟠 HIGH (fix before launch)

### SEC-H1 — Admin GVA/import routes use an inconsistent `id===1 || is_admin` check and live outside the hardened superadmin group (privilege mismatch + SSRF-capable action)
**Files:** `app/Http/Controllers/Api/GvaController.php:14–21, 163`; `routes/api.php:271–275`

Five `admin/*` routes are inside the plain `auth:sanctum` group and gate on `($user->id === 1 || $user->is_admin)` — the boolean `is_admin` column and a hardcoded id — **not** the `role` field used by `EnsureSuperAdmin` (`app/Http/Middleware/EnsureSuperAdmin.php:19`). This means `role='admin'` users are denied here but allowed in the panel, and `is_admin=true, role='user'` users get admin import powers. `adminImportarManual` (`GvaController.php:85–107`) takes an arbitrary `url` and queues a server-side fetch (SSRF surface) outside the rate-limited superadmin group.

**Fix:** move these routes into the `superadmin` group (`routes/api.php:279`) and delete the inline check, or replace it with `abort_unless($user->isAdmin(), 403)` (the `role`-based helper at `User.php:171`).

### SEC-H2 — Document `view`/`thumbnail` have no ownership check and signed URLs aren't bound to a user
**Files:** `routes/api.php:114–117`; `app/Http/Controllers/Api/UserDocumentController.php:173–191, 213–215`

Both routes use only `signed` middleware (no `auth:sanctum`) and the controller methods skip the `assertOwns()` that every other method in the file uses. The signature isn't bound to a user, and a signed `thumbnail_url` is emitted for every document in list responses. A leaked/shared link (Referer, proxy, screenshot) grants full file access to anyone for the whole TTL.

**Fix:** add an ownership assertion as defense-in-depth and bind the signed URL to a `uid`:
```php
// when generating: URL::temporarySignedRoute('documents.view', now()->addMinutes(10), ['document'=>$id,'uid'=>$user->id]);
// in view(): abort_unless((int) $request->query('uid') === $document->user_id, 403);
```
Consider shortening the TTL.

### SEC-H3 — Cloud import (Drive / Microsoft 365) bypasses MIME and size validation
**File:** `app/Http/Controllers/Api/Integrations/AbstractCloudImportController.php:111–158` (esp. 137–139)

Direct uploads enforce `mimes:`+`max:` (`UserDocumentController.php:30–35`), but the cloud-import path derives the extension from the remote filename and stores the bytes with **no allow-list and no per-file size cap** (storage quota is checked, but not `documents.allowed_ext` / `documents.max_kb`). A user can import a `.php`/`.svg`/`.html` or a multi-GB file. (UUID filename does prevent traversal.)

**Fix:**
```php
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin');
abort_unless(in_array($ext, config('documents.allowed_ext'), true), 422, 'Tipo de archivo no permitido.');
abort_if(strlen($file['contents']) > config('documents.max_kb') * 1024, 422, 'Archivo demasiado grande.');
```

### SEC-H4 — AI endpoints have no per-minute rate limit (cost/abuse)
**File:** `routes/api.php:255–268`

`ai/conversations/{id}/message`, `ai/flashcards/from-tema`, `ai/flashcards/from-document`, `ai/scores/{tema}/simulacro` have **no `throttle:`**. Each call hits paid Anthropic/Voyage APIs. The only guard is a per-day count, checked only on the chat route (`AiConversationController.php:65`). A script can burn the daily budget in seconds and hammer the provider concurrently.

**Fix:** add a named limiter and apply it:
```php
RateLimiter::for('ai', fn (Request $r) => Limit::perMinute(10)->by($r->user()->id));
// ...->middleware('throttle:ai') on every ai/* write route
```

---

## 🟡 MEDIUM (fix soon)

### SEC-M1 — Cloud-integration OAuth `state` nonce is generated but never validated (replay/CSRF)
**File:** `app/Http/Controllers/Api/Integrations/AbstractCloudImportController.php:55–59, 183–198`
`state` is an encrypted `{uid, nonce, exp}` blob; on callback only `exp`+`uid` are checked and the `nonce` is discarded — so a captured callback URL can be replayed within the 10-min window and `state` isn't bound to the initiating session. (Encryption with the app key prevents `uid` forgery, so this is replay/interception, not forgery.)
**Fix:** persist the nonce at `connect()` (`Cache::put("oauth_nonce:$nonce", $uid, 600)`) and `Cache::pull` it on callback (single-use).

### SEC-M2 — Impersonation issues a full-privilege (`*`) token with no DB-level expiry
**File:** `app/Http/Controllers/Api/SuperAdmin/UsuariosController.php:181–210`
The impersonation token has default abilities and only a 2-hour **cache** marker; if the cache entry is evicted the token still works but is no longer auditable as impersonation.
**Fix:** `createToken('impersonation', ['*'], now()->addHours(2))` (explicit expiry) and store the admin linkage in a DB column, not only cache.

### SEC-M3 — File-upload endpoints have no rate limit (queue/storage DoS)
**File:** `routes/api.php:175, 193, 196`
Uploads spawn `ProcessDocumentJob` + pdfinfo/Imagick + extract/chunk/embed jobs; no throttle means a user can flood the queue. Add `throttle:30,1`.

### SEC-M4 — Participant routes (personal names) aren't throttled; the `public-list` limiter is defined but applied to nothing
**Files:** `routes/api.php:211, 214`; `app/Providers/AppServiceProvider.php:53–56`
`participantes/{proceso}` returns `nombre_gva`, `centro_nombre`, etc. A `public-list` limiter (40/min/IP) exists but `grep` shows it is used by **zero** routes → the personal-data endpoint can be scraped at full speed.
**Fix:** apply `throttle:public-list` to `participantes/{proceso}` and `participantes/{proceso}/cambios`.

### SEC-M5 — No `config/cors.php`; CORS falls back to wildcard origin
**File:** (missing) `config/cors.php`
The API is stateless/bearer (`supports_credentials=false`), so wildcard isn't directly exploitable, but it's overly permissive and undocumented.
**Fix:** publish `config/cors.php`, restrict `allowed_origins` to the production SPA domain(s), keep `supports_credentials=false`.

### SEC-M6 — Normativa PDFs are stored on the public disk with predictable raw URLs
**File:** `app/Http/Controllers/Api/SuperAdmin/NormativaController.php:92, 114, 174`
Unlike user documents (private disk + signed routes), normativa PDFs are world-readable at `/storage/normativa/...`. Upload is admin-only and validated, so the issue is *unauthenticated public exposure* of these files. If they're meant to be public this is acceptable; otherwise move to the private disk + signed/auth route.

---

## 🟢 LOW (nice to have)

- **SEC-L1** — `mimes:` validates by extension, not content (`UserDocumentController.php:32`). Files are stored under UUID names on a non-executed disk, so risk is low; add `mimetypes:` for defense-in-depth.
- **SEC-L2** — `config/documents.php:9` `documents.disk` default has both ternary branches return `'spaces'`, so local dev always targets S3 even with `FILESYSTEM_DISK=local`. Set the false branch to `'local'`.
- **SEC-L3** — `SistemaController::failedJobs()` (`:88–97`) returns truncated exception text; superadmin-only, but consider redacting.
- **SEC-L4** — Hardcoded super-admin promotion email `j.madrid@loggex.es` in source (`AuthController.php:141–143`); move to config/env.
- **SEC-L5** — Web OAuth callback accepts GET **and** POST (`routes/web.php:22`); restrict to GET. (The Socialite login flow itself is secure — non-stateless, session-validated `state`.)

## ✅ Verified good (no action)
No committed `.env`; `.gitignore` covers `.env*`, `/vendor`, `/node_modules`, `/storage/*.key`; no hardcoded secrets anywhere in `app/config/routes/database/resources`; `config/app.php:42` debug defaults `false`; OAuth tokens in `user_integrations` cast `'encrypted'` + `$hidden` (`UserIntegration.php:20,25–26`); user documents served only via 15-min `temporarySignedRoute`; jobs take an integer id + re-fetch and every dispatch site is ownership-scoped; `RagService` always scopes `where('user_id',…)`; Stripe webhook signature-verified; login/register/exchange/geocode/distances + superadmin group all throttled; ownership checks correct in `UserDocumentController`, `UserFolderController`, `AiConversationController`, `OposicionPreparacionController`, `ConvocatoriasController::toggleAlert`, `ScoringController`, `FlashcardController`, `TablonController`.

---

# PART 2 — UX AUDIT

## Per-page consistency score

| Page | File | Score |
|---|---|---|
| Oposición — Mi Preparación | `oposicion/MiPreparacion.jsx` | **10** ⭐ gold standard |
| Oposición — Normativa | `oposicion/Normativa.jsx` | **10** ⭐ |
| Oposición — Convocatorias | `oposicion/Convocatorias.jsx` | **10** ⭐ |
| Login / OAuth | `auth/LoginPage.jsx` | **9** |
| Mode selector + nav | `dashboard/Dashboard.jsx` | **8** |
| Mis Documentos | `dashboard/MisDocumentos.jsx` | **8** |
| Tablón | `tablon/TablonList.jsx` | **7** |
| Filtros | `FiltersPanel.jsx` | **7** |
| Dashboard / Inicio | `dashboard/DashboardHome.jsx` | **6** |
| Mi Perfil | `dashboard/UserProfile.jsx` | **6** |
| Vacantes — Kanban | `KanbanBoard.jsx` + `VacancyCard.jsx` | **5** |
| Vacantes — Lista / Mi Lista | `ListBoard.jsx`, `SortableRows.jsx`, `VacancyRow.jsx` | **5** |
| Centros — lista + detalle | `centros/CentrosList.jsx`, `centros/CentroDetail.jsx` | **5** |
| Superadmin (todas) | `superadmin/*` | N/A (intentional dark back-office theme; internally consistent) |

## What the gold standard does right (the target)
- `font-heading` (Bricolage) on all titles; one card style `rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200`; centralized pill status chips on brand/amber/slate; `bg-brand-600 rounded-lg` primary buttons; rich empty states (emoji + heading + subcopy); branded gradient feature cards (`from-brand-600 to-brand-700 shadow-brand`); shared `inputCls` with `focus:border-brand-400 focus:ring-brand-400`.

## 🥇 Bolsa vs Oposición gap — the headline UX problem
The two modes look built by different designers. Highest-ROI fixes first:

**1. Typography (P1, highest ROI, lowest effort).** `font-heading` appears in **0 Bolsa files**; every Bolsa heading silently falls back to body font.
- `dashboard/DashboardHome.jsx` card titles (42, 113, 349, 447, 468): add `font-heading`.
- `centros/CentrosList.jsx:67`, `centros/CentroDetail.jsx:77` (+H2s 124/155/189), `tablon/TablonList.jsx:89` (+modal `:36`), `dashboard/UserProfile.jsx:153`: `text-lg font-bold` → `font-heading text-xl font-bold`.
- `KanbanBoard.jsx:85`, `ListBoard.jsx:131,162,186`: add `font-heading` to column/section titles.

**2. Color language (P3).** Bolsa uses raw `green-100/red-100/blue-100`; Oposición uses light `-50` tones.
- `DashboardHome.jsx:7–11, 94–98, 372–376`: three near-duplicate `ESTADO_*_STYLES` maps → unify into one shared constant and retone `green-100/700→emerald-50/700`, `red-100/700→rose-50/700`, `blue-100/700→blue-50/700`.
- `CentroDetail.jsx:138` `bg-green-100→bg-emerald-50`; `TablonList.jsx:39`/`UserProfile.jsx:71,224` `text-green-600→text-emerald-600`, `UserProfile.jsx:225` `text-red-600→text-rose-600`.
- `CentrosList.jsx:136` & `CentroDetail.jsx:80` `bg-brand-100→bg-brand-50`.

**3. Card chrome (P2).** Bolsa list/kanban primitives use `border`+`rounded-xl/lg` instead of `ring-1`+`rounded-2xl`.
- `VacancyCard.jsx:74,76`: `'rounded-xl border …'` + `border-slate-200 hover:border-slate-300` → `'rounded-2xl … shadow-sm'` + `ring-1 ring-slate-200 hover:ring-slate-300`.
- `VacancyRow.jsx:34,36`: `rounded-lg border` → `rounded-xl` + `ring-1 ring-slate-200 hover:ring-slate-300`.

**4. Empty states (P5).** `CentrosList.jsx:122` & `TablonList.jsx:118` are `ring-1` cards but lack the emoji + headline hierarchy; Kanban columns (`KanbanBoard.jsx:200,222,246,270,276`) mix dashed-border and bare `<p>` — unify all four columns and add emoji+headline to Centros/Tablón empties (match `Normativa.jsx:121–124`).

**5. Micro (P4/P7).** `VacancyCard.jsx:201` status buttons `rounded-md`→`rounded-lg`; standardize one input focus ring (`LoginPage.jsx:156`, `VacancyCard.jsx:182`, `FiltersPanel.jsx:182` use the lighter `ring-brand-200` variant) on `focus:border-brand-400 focus:ring-brand-400`.

*(Superadmin note: deliberately separate dark theme — internally consistent. Optional: swap `sky-600`→`brand-600` and add `font-heading` to feel part of the same product.)*

## Mobile responsiveness
- **HIGH** `VacancyCard.jsx:201` — kanban action buttons `px-2 py-1 text-[11px]` (~24px) are far below the 44px touch minimum, 4 in a row on the core mobile interaction. → `py-2 min-h-[44px]`, allow wrapping.
- **MEDIUM** `MisDocumentos.jsx:307` and `ExportPanel.jsx:182` tables not wrapped in `overflow-x-auto` (superadmin tables do this correctly). → wrap in `<div className="overflow-x-auto">`.
- **LOW** modals never go full-screen on mobile (e.g. `ExportPanel.jsx:142` `max-w-3xl`). → add `h-full w-full sm:h-auto sm:max-w-...`.
- ✅ Kanban grid responsive; dedicated mobile nav menu; dnd-kit TouchSensor with press-delay.

## Loading states
- **HIGH (optimistic UI)** kanban is not optimistic — `savePreferences` (`useUserList.js:36`) updates cache only in `onSuccess` (`:43`), no `onMutate`; dragging waits for the round-trip. → add `onMutate` `setQueryData` + `onError` rollback (the pattern `useListSync`/mode-selector already use).
- **MEDIUM (consistency)** skeletons exist only in superadmin (`superadmin/ui.jsx:4`); the entire user app uses plain "Cargando…" (`app.jsx:380,501`, `CentrosList.jsx:121`, `CentroDetail.jsx:64`, `TablonList.jsx:117`, `MisAnuncios.jsx:40`, `SpecialtySelector.jsx:59`). → reuse `Skeleton` primitives on vacancy list, centros grid, tablón.

## Empty states
- **MEDIUM** `DashboardHome.jsx:120` shared `Empty` is a bare muted `<p>` (used 216/231/471); line 231 "Aún no has añadido especialidades" should be a CTA to `/dashboard/especialidades`.
- **MEDIUM** Kanban neutral-empty (`KanbanBoard.jsx:199`) and Tablón (`TablonList.jsx:118`, `MisAnuncios.jsx:41`) lack icon/CTA.
- Reference good pattern: `MisDocumentos.jsx:447–456`, `MiPreparacion.jsx:728`.

## Forms
- **MEDIUM** `tablon/TablonForm.jsx` — `<label>`s have no `htmlFor`, inputs no `id` (67/68, 74/75, …); no required markers; validation is submit-only with a single generic error (`:147`). → associate labels, mark required, show inline field errors.
- **MEDIUM** Most mutations surface only a generic `friendlyMessage` banner (`CentrosList.jsx:118`, `app.jsx:300`).
- **LOW** `type="date"` inputs (`AdminConvocatorias.jsx:187`, `oposicion/Convocatorias.jsx:306`, `Normativa.jsx:259`, …) render in browser locale, not guaranteed `dd/mm/aaaa`; `MisEspecialidades.jsx:199` number input has no min/max. (Number inputs in `FiltersPanel.jsx:132–141` and `MiPreparacion.jsx:586` are good.)

## Navigation
- ✅ Active `NavLink` state; coming-soon items visibly disabled.
- **LOW** no breadcrumbs; `ComingSoon` (`app.jsx:491`) and `CentroDetail` have no "volver" affordance.
- **LOW** external links use `rel="noreferrer"` only — standardize on `rel="noopener noreferrer"` (`CentroDetail.jsx:104`, `VacancyRow.jsx:109`, `VacancyCard.jsx:47`, `DashboardHome.jsx:298,314`, `MisDocumentos.jsx:367`).

## Accessibility
- **HIGH** No modal has a real **focus trap** — Tab can leave the dialog and focus isn't restored on close (user modals handle Escape via `useEscapeKey`, but trapping is missing). → add focus-trap + return focus to trigger.
- **MEDIUM** Several modals lack Escape + `role="dialog"`: free-plan limit modal (`app.jsx:422`), `TablonList.jsx:34` preview, all superadmin modals (`AdminConvocatorias.jsx:165`, `AdminMonitorDocs.jsx:103`, `AdminCalendario.jsx:201`, `AdminNormativa.jsx:168`).
- **LOW** `text-slate-400` used for meaningful text on white (~2.8:1, below WCAG AA 4.5:1) — `DashboardHome` Empty, `CentrosList.jsx:123`, `app.jsx:301`, `VacancyRow.jsx:166,182`. → use `slate-500/600` for information.
- **LOW** `<img>` with empty `alt` (`MisDocumentos.jsx:291` thumbnail clickable preview, `Dashboard.jsx:148` avatar). → describe the doc / `alt={user.name}`.
- ✅ Icon-only buttons generally have `aria-label`; decorative emoji `aria-hidden`; toggle uses `role="switch"`+`aria-checked`.

## Performance / N+1 (backend)
- **HIGH** `CentroController.php:61` — proximity search does `->get()` on the entire filtered `centros` table then Haversine-filters in PHP before paginating in-memory (user-reachable). → push Haversine into SQL with bounding-box prefilter + `LIMIT/OFFSET`.
- **HIGH** `ListadoNotificacionService.php:95` — `notifyEliminados` loads the whole `users` table to filter in PHP (the sibling `notifyParticipantes:73` already uses DB-side `whereRaw`). → use `whereRaw('LOWER(nombre_gva) IN (...)')`.
- **MEDIUM** `DistanceController.php:88–89` — `DistanceCacheRepository::find()` called inside nested loops → hundreds of single-row queries; the repo already has `forVacancies()` batch — preload into a map.
- **MEDIUM** `PreferenceController.php:54–55` — `updateOrCreate` in a loop (2N queries) and `BulkPreferencesRequest.php:17` has no `max:` cap → use chunked `upsert()` + add `max:`.
- **MEDIUM** `ConvocatoriaMonitorService.php:137` — `existsSimilar` runs `similar_text` over all titles (scheduled, not hot). → SQL `LIKE` prefilter.
- **LOW** `UserProfileController.php:783–784` calls `impersonationState()` twice; `DashboardController.php:32–50` runs ~12 separate COUNTs (admin-only). → collapse into conditional sums.
- ✅ Bundle is a single ~1MB JS chunk — heavy routes (kanban, superadmin) are **not code-split**; consider `React.lazy` per route (perf, not blocking).

## Copy / microcopy
- **HIGH** Raw English enum values leak into the superadmin UI: `AdminSuscripciones.jsx:55` `{s.status}` (active/trialing/past_due/canceled) while the same screen's filter shows Spanish labels; `AdminUsuarios.jsx:75` `{u.plan_status}`, `AdminUsuarioDetalle.jsx:60,121`. → shared Spanish status map.
- **MEDIUM** Raw `{u.role}` (`AdminUsuarios.jsx:77`, `AdminUsuarioDetalle.jsx:61`) → "Usuario/Administrador/Superadmin"; `AdminMonitorDocs.jsx:281` raw `{s.type}` despite a `TYPE_LABELS` map at `:81`.
- **LOW** `AdminConvocatorias.jsx:91` raw `{c.estado}` ("en_proceso" with underscore) → `ESTADO_LABEL` map; "Cargar más" (`KanbanBoard.jsx:192`) vs "Cargar más vacantes" (`ListBoard.jsx:215`) — standardize.
- ℹ️ `ExportPanel.jsx` deliberately uses Valencian headers to mirror the official "Llista de vacants" export — confirm intended.
- ✅ No "Loading/Save/Cancel" English leaks in user-facing JSX; placeholders are specific Spanish; consistent "tú" tone.

---

# PART 3 — Prioritized remediation roadmap

### Sprint 1 — Security must-fix (before any launch)
1. **SEC-C1** authenticate/authorize `user-lists/*` (CRITICAL, active PII leak).
2. **SEC-H1** move `admin/*` GVA routes into superadmin group; drop `id===1||is_admin`.
3. **SEC-H2** ownership + per-user binding on document `view`/`thumbnail`.
4. **SEC-H3** validate cloud-import file type/size.
5. **SEC-H4** per-minute throttle on all AI write endpoints.

### Sprint 2 — Security hardening + top UX
6. SEC-M3/M4 throttle uploads + apply `public-list` to participant routes; SEC-M1 single-use OAuth nonce; SEC-M5 publish restrictive `config/cors.php`; SEC-M2 impersonation token expiry; SEC-M6 decide normativa PDF privacy.
7. **UX-P1** add `font-heading` to all Bolsa/shared titles (biggest visual ROI).
8. **UX kanban**: 44px touch targets + optimistic `onMutate`.
9. **A11y**: modal focus-trap; Escape + `role="dialog"` on remaining modals.
10. **Copy**: map English enum values in superadmin.

### Sprint 3 — UX polish + perf
11. UX-P2/P3/P5 unify card chrome, color tokens (`-50`), empty states (emoji+headline+CTA).
12. Skeleton loaders across the user app; contrast fix (`slate-400`→`500/600`); `rel="noopener noreferrer"`.
13. Perf: SQL Haversine in `CentroController`; DB-side `notifyEliminados`; batch distance lookups; `upsert` + `max:` in preferences; route-level code-splitting.

---

*Audit produced by four parallel review passes (auth/authorization, secrets/config/uploads/rate-limiting, visual consistency, mobile/a11y/perf/copy). Every finding cites a concrete file:line in the audited tree.*

---

# PART 3 — LEGAL & CUMPLIMIENTO (addendum)

The original audit (PARTs 1–2) covered **technical security and UX only**. It did **not** assess legal/regulatory compliance. This addendum closes that gap for an EU SaaS handling personal data, subject to **RGPD (UE 2016/679)**, **LOPDGDD (LO 3/2018)**, **LSSI-CE (Ley 34/2002)** and the **AI Act (Reglamento UE 2024/1689)**.

## State before this sprint

Grep over `resources/ app/ routes/` for cookies/privacy/legal/consent returned **zero** compliance artifacts. There was no cookie banner, no privacy policy, no aviso legal, no terms, no registration consent, no account self-deletion (right to erasure), no data export (portability), and no disclosure of third-party processors (Stripe, Anthropic, Voyage AI, Google, Microsoft) or AI usage.

## Implemented in this sprint

| # | Item | Marco | Estado |
|---|------|-------|--------|
| L1 | Política de Privacidad (`/legal/privacidad`) | RGPD art. 13-14 | ✅ Implementado (texto plantilla) |
| L2 | Aviso Legal (`/legal/aviso-legal`) | LSSI-CE art. 10 | ✅ Implementado (texto plantilla) |
| L3 | Banner de cookies con consentimiento granular | LSSI-CE art. 22.2 | ✅ Implementado |
| L4 | Términos y Condiciones (`/legal/terminos`) | LSSI-CE | ✅ Implementado (texto plantilla) |
| L5 | Política de Cookies (`/legal/cookies`) | LSSI-CE art. 22.2 | ✅ Implementado |
| L6 | Casilla de consentimiento en registro + sello `terms_accepted_at` | RGPD art. 7 | ✅ Implementado |
| L7 | Borrado de cuenta (`DELETE user/account`) + UI | RGPD art. 17 | ✅ Implementado |
| L8 | Exportación de datos (`GET user/export`) + UI | RGPD art. 20 | ✅ Implementado |
| L9 | Disclosure de encargados/subencargados + transferencias internacionales | RGPD art. 28, 44-49 | ✅ En política de privacidad |
| L10 | Aviso de IA en el chat + disclaimer de errores | AI Act art. 50 (aplica 02/08/2026) | ✅ Implementado |
| L11 | Monitorización de errores (Sentry) backend + frontend, PII off, tracing tras consentimiento | Interés legítimo / seguridad | ✅ Implementado |

## Pendiente (requiere intervención humana — NO automatizable)

- **Completar los datos del titular** en `resources/js/components/legal/info.js`: razón social, NIF, domicilio fiscal, proveedor de hosting. Los textos llevan placeholders `[...]`.
- **Revisión por un abogado/DPO** de la redacción legal vinculante (base jurídica de cada tratamiento, contratos de encargado del tratamiento con cada proveedor, registro de actividades RGPD art. 30).
- **Configurar el DSN de Sentry** (`SENTRY_LARAVEL_DSN`, `VITE_SENTRY_DSN`) en producción; sin DSN, la integración es un no-op.
- Valorar (probable no-aplica) decisiones automatizadas del scoring respecto a RGPD art. 22.
