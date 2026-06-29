# Doccentia — Audit Sprint D: Modo Docente en Activo

**Repo:** javimaha-debug/vacante-docente · **Branch audited:** `main` (at `c778be3`)
**Date:** 2026-06-29 · **Scope:** ONLY the Modo Docente code added in Sprint D (12 controllers, `DocenteAiService`, 12 migrations, 6 React pages, ~50 routes). Previous sprints were **not** audited.
**Status:** findings only — nothing was fixed.

---

## Executive summary

Modo Docente is **well-built on ownership fundamentals**: every route-model-bound `update`/`destroy`/`show`/custom action checks `user_id` ownership (`abort_if($model->user_id !== $request->user()->id, 403)`), and the **shared bank moderation is correctly enforced** — `moderado` defaults to `false`, `compartir` never flips it, every public read gates on `moderado = true`, and only superadmins can approve. No classic write-IDOR was found.

However there are **real gaps** around the three things this sprint specifically introduced: the **student-PII filter is incomplete and only wired to one of the AI endpoints**, **one AI endpoint is missing the rate-limit**, the **AI generators are ungated by plan and have no daily cap**, and several `store()` methods accept **foreign-key IDs without verifying ownership** (cross-user association + minor info disclosure via AI generation). On the UX side, the docente pages average **~7/10** vs the 10/10 Oposición standard, with a consistent set of issues: titles don't use `font-heading`, the AI disclaimer is a non-canonical inline copy, the weekly schedule grid breaks on mobile, and empty states are thin.

| Area | CRITICAL | HIGH | MEDIUM | LOW |
|---|---|---|---|---|
| Security | 0 | 3 | 4 | 4 |
| UX / Consistency | 0 | 2 | 5 | 4 |

### Page scores (1–10 vs Oposición = 10)

| Page | Score | One-line |
|---|---|---|
| AsistentePage | 9 | Thin wrapper over the Oposición Asistente — inherits its quality |
| MeritosPage | 8 | Baremo total is prominent & clear; minor token/heading gaps |
| ProgramacionPage | 7 | Solid structure; no `font-heading`, thin empty states |
| BancoPage | 7 | Good discovery/copy; no copy confirmation toast |
| HorarioPage | 6 | `min-w-[600px]` grid breaks mobile; progress alerts unclear |
| RecursosPage | 5 | Generators work but disclaimer non-canonical, output read-only |

---

# PART 1 — SECURITY

## 🟠 HIGH

### SEC-D1 — Student-PII filter is incomplete and only covers one endpoint
**File:** `app/Http/Controllers/Api/Docente/AdaptadorController.php:24`
```php
if (preg_match('/\b(DNI|NIE|NIF|[A-Z]\d{7}[A-Z]|\bexpediente\b|\bnotas?\b.*alumno|\balumno.*notas?\b)/i', $data['texto'])) {
```
Two problems:
1. **The pattern misses a bare Spanish DNI.** A DNI is `8 digits + control letter` (e.g. `12345678Z`). The only numeric pattern here is `[A-Z]\d{7}[A-Z]` (letter + 7 digits + letter), which matches an **NIE** but **not** a DNI number that appears without the literal word "DNI". So pasting `Alumno 12345678Z, calificación 3` passes the filter and is sent to Anthropic.
2. **It is the only AI endpoint with any PII check.** `situaciones/generar` (`contexto_mundo_real`, free text) and the `asistente` chat send free text to the model with no scrubbing. The adaptador is the main "paste student text" vector, so partial coverage there is the worst offender, but the control is inconsistent.

**Fix:** broaden the number pattern to also catch DNI: add `\d{8}[A-Z]` (and optionally email/phone), e.g. `(?:[XYZ]?\d{7,8}[A-Z])`. Extract the check into a reusable `DocenteAiService::containsStudentPii(string)` and call it from every free-text AI entry point (`adaptarTexto`, `generateSituacionAprendizaje` contexto, assistant docente context). Document the limitation (names can't be regex-detected) in the UI disclaimer.

### SEC-D2 — IDOR: `examenes/generar` reads another user's asignatura/unidad
**Files:** `app/Services/DocenteAiService.php:120-129` (loader) ← `app/Http/Controllers/Api/Docente/ExamenesController.php:54` ← route `routes/api.php:354`
```php
// DocenteAiService::generateExamen
$asignatura = isset($params['asignatura_id']) ? DocenteAsignatura::find($params['asignatura_id']) : null;   // no user scope
$unidad     = isset($params['unidad_id'])     ? DocenteUnidad::find($params['unidad_id'])         : null;   // no user scope
```
`ExamenesController::generar` validates `asignatura_id`/`unidad_id` as `nullable|integer` only (lines 44, 46) and passes them straight through. A user can supply **any** id; the model loads another teacher's row and the generated exam echoes that asignatura's `nombre`/`curso` and the unidad's `titulo` back to the attacker — cross-user information disclosure. Limited to those metadata fields, hence HIGH not CRITICAL.

**Fix:** scope the lookups to the caller, e.g. `DocenteAsignatura::where('user_id', $userId)->find(...)` / `DocenteUnidad::whereHas('programacion', fn($q)=>$q->where('user_id',$userId))->find(...)`, and pass `$userId` into the loader.
*Note:* `DocenteAiService::generateActividad` (line 31) has the same unscoped `DocenteUnidad::findOrFail` but is **dead code** — no route reaches it. Scope it too (or delete it) to prevent a future live IDOR.

### SEC-D3 — AI endpoint missing rate-limit: `programaciones/{id}/adaptar`
**File:** `routes/api.php:329` ← `app/Http/Controllers/Api/Docente/ProgramacionesController.php:97` (`$this->ai->adaptarProgramacion(...)`, a real Anthropic call)
```php
Route::post('programaciones/{programacion}/adaptar', [DocenteProgramacionesController::class, 'adaptar']); // no throttle
```
The other four AI endpoints correctly carry `->middleware('throttle:ai-generate')` (`rubricas/generar` :340, `situaciones/generar` :347, `examenes/generar` :354, `adaptar-texto` :370). `adaptar` is an unthrottled LLM call — abuse/cost risk.
**Fix:** append `->middleware('throttle:ai-generate')` to the route at line 329.

## 🟡 MEDIUM

### SEC-D4 — Docente AI generators are not plan-gated and have no daily cap
**Files:** `routes/api.php:302` (group has no `plan:` middleware); `app/Services/DocenteAiService.php:256-265` (`recordUsage` only increments `AiUsage`, never checks a ceiling).
The chat path enforces `config('ai.daily_message_limit')`; the docente generators record usage but never enforce a daily limit, and the whole `docente` group is open to any authenticated user regardless of plan. Combined with SEC-D3 this is an unmetered LLM cost surface for free accounts.
**Fix:** add the relevant `plan:`/feature middleware to the docente AI routes (if docente is a paid feature) and enforce the daily cap inside `DocenteAiService` before calling Anthropic.

### SEC-D5 — `store()` methods accept foreign keys without ownership verification
**Files / lines:**
- `ProgramacionesController::store` — `asignatura_id` (validated `nullable|integer`, no `exists`/owner check).
- `SesionesController::store` — `unidad_id` accepted without verifying it belongs to the user (grupo *is* checked).
- `RubricasController::store:32`, `SituacionesController::store:39`, `ExamenesController::store:32-33` — `asignatura_id`/`unidad_id` unverified.

A user can attach their own child record to **another user's** parent id. Low data-theft impact (the child is theirs) but it corrupts cross-user relationships and can surface another user's asignatura in joins.
**Fix:** for each provided FK, `Model::where('user_id', $request->user()->id)->findOrFail($id)` (or `whereHas` for nested) before create.

### SEC-D6 — `document_id` accepted without ownership/`exists` check
**Files:** `MeritosController::store:36` & `update:56`; `ProgramacionesController` store/update.
`document_id` is `nullable|integer` with no `exists:user_documents,id` and no owner check, so a mérito/programación can reference another user's uploaded document id.
**Fix:** validate `exists:user_documents,id` **and** confirm `UserDocument::where('user_id', …)->find($document_id)`.

### SEC-D7 — `generarSesiones` doesn't verify the unidad belongs to the grupo's plan
**File:** `app/Http/Controllers/Api/Docente/UnidadesController.php:76-85`
Both `unidad` (line 78) and `grupo` (line 85) are correctly owner-checked, so this is **not** an IDOR — but there's no check that the `unidad` and `grupo` belong to the same asignatura/programación, so sessions can be generated against a semantically unrelated group.
**Fix:** assert `unidad->programacion->asignatura_id === grupo->asignatura_id` (or equivalent) before generating.

## 🟢 LOW

### SEC-D8 — `puntos_calculados` is mass-assignable
**File:** `app/Models/DocenteMerito.php:14` (`'puntos_calculados'` in `$fillable`).
Currently **not exploitable** for baremo manipulation: `MeritosController::store`/`update` don't include it in their validated arrays, and `meritos/baremo` recomputes the total **server-side** from fixed rules (`DocenteAiService::calcularMeritos:178-219`, inputs capped per `BAREMO`). It's a latent footgun if a future endpoint passes request data directly.
**Fix:** remove `puntos_calculados` from `$fillable` (it's always set by the service).
**Baremo verdict:** ✅ the baremo **cannot** be manipulated by the user today — the total is derived from `horas`/item-count/dates with per-category `max` caps, not from any client-supplied score.

### SEC-D9 — Raw-SQL string concatenation in usage accounting
**File:** `app/Services/DocenteAiService.php:261-263`
```php
'tokens_input' => \DB::raw('COALESCE(tokens_input,0) + ' . ($result['tokens_input'] ?? 0)),
```
Values come from Anthropic's response cast to int, so not injectable today, but concatenating into `DB::raw` is fragile.
**Fix:** use `increment()` / bound expressions instead of string concatenation.

### SEC-D10 — File exports are placeholders (no signed-URL surface yet)
**Files:** `ExamenesController::export:68-75` and `MeritosController::export:79-89` both return JSON with `"…disponible próximamente"`. There is **no actual PDF/Word download**, so no storage paths leak today (the audit item passes by absence).
**Fix / guard rail:** when implemented, stream via `Storage::temporarySignedRoute`/signed routes bound to `user_id` (mirror `UserDocumentController::view`) — never return raw disk paths.

### SEC-D11 — No `show`/index route for some bindings (defensive only)
`grupos/{grupo}` and `horario/{horario}` have no `GET` route; their controllers do contain ownership checks, so this is a completeness note, not a vulnerability.

### ✅ What passed
- **Ownership on all bound mutations** (`update`/`destroy`/`show`/`compartir`/`adaptar`/`duplicar`/`progresoGrupo`/`generarSesiones`/`export`) — explicit `abort_if(... user_id ...)`.
- **Bank moderation**: `moderado` default `false` (`…_create_docente_recursos_compartidos_table.php:16`), `compartir` never sets it (`RubricasController:86`), reads gate on `moderado` (`BancoController:19,39,46,68`), `moderar` superadmin-only (`routes/api.php:387` group `['auth:sanctum','superadmin',…]`). **Nothing is public without superadmin approval.**
- **`usar` copy pattern** replicates into the caller's account with `user_id` reset and `es_publica=false` (`BancoController:117-146`) — no cross-user write.
- **Author privacy**: `decorateRecurso` never exposes `user_id` (`BancoController:88-111`).

---

# PART 2 — UX (scored vs Oposición 10/10)

### ProgramacionPage.jsx — 7/10
- Solid tabbed structure; "Adaptar a nuevo centro" flow works and shows a disclaimer (line 180).
- Titles use plain `font-bold` not `font-heading` (lines 143, 244, 260, 271).
- Empty state for programaciones is thin (lines 281-282) vs the emoji+headline+hint Oposición pattern.

### HorarioPage.jsx — 6/10 (lowest-functioning)
- **Weekly grid breaks on mobile:** `min-w-[600px]` (line 161) forces horizontal scroll with no affordance.
- **Progreso por grupo alerts unclear** (lines 50-72): "at-risk" is conveyed only by color (rose); `Proyección: {data.proyeccion_fin}` (line 69) has no explanatory label, and there's no explicit "vas con retraso/adelanto" message.
- Session modal "Nota rápida / ¿Qué se vio realmente?" (lines 32) is ambiguous (required? purpose?); no success feedback after marking.
- Titles not `font-heading` (lines 155, 190, 212).

### RecursosPage.jsx — 5/10 (AI generators hub)
- All four generators follow a clean generate→review→save flow and **each shows a disclaimer** (rúbrica 73, situación 197, examen 327, adaptador 474).
- **Adaptador modes are correct**: `simplificar / ampliar / ACIS` (lines 408-412), word-count helper (440-442), result + changes list (472-496).
- Generated output is **read-only before save** (can't tweak a criterion/question) — friction for real use.
- Exam preview truncates questions with `slice(0,3)` (poor preview).
- Title not `font-heading` (line 513). See CONSISTENCY for the disclaimer-source issue.

### BancoPage.jsx — 7/10
- Good discovery (search + type filters, lines 132-152), rich empty state distinguishing "bank empty" vs "no results" (162-168), clear rating + copy on each card.
- **No confirmation after copy** (`usar`) — the action succeeds silently; add a toast/inline "copiado a Mis recursos".
- Title not `font-heading` (line 128).

### MeritosPage.jsx — 8/10 (best non-wrapper page)
- **Baremo total is displayed clearly**: prominent `brand-50` card, `text-3xl font-bold text-brand-700`, centered, with per-category breakdown (lines 151-166) and a secondary total in the filter bar (222). Disclaimer shown (147).
- "Export baremo" lives only inside the panel after calculation, not in the toolbar.
- Conditional fields in the modal (horas/créditos for `formacion`) aren't visually signposted.
- Titles not `font-heading` (lines 199, 133).

### AsistentePage.jsx — 9/10
- Re-exports the Oposición `Asistente`, inheriting its 10/10 patterns. Only gap: no docente-specific context hint, so the user can't tell which "mode" the chat is in.

---

# PART 3 — CONSISTENCY

| Check | Result |
|---|---|
| **`font-heading` (Bricolage) on titles** | ❌ **None** of the 6 pages use it (Programacion 143/244/260/271, Horario 155/190/212, Recursos 513, Banco 128, Meritos 133/199). Oposición uses it via `oposicion/shared.jsx:71 SectionTitle`. |
| **Design tokens (brand/teal/amber/emerald)** | ✅ Mostly good — `brand-*`, `amber-*`, `emerald-*` used consistently across all pages. |
| **Empty states** | ⚠️ Inconsistent — Banco is rich (162-168); Programacion/Horario/Recursos/Meritos are minimal text. |
| **AI disclaimers present on every AI output** | ✅ Present on all generators (Recursos 73/197/327/474, Programacion 180, Meritos 147). |
| **AI disclaimer canonical source** | ❌ Recursos (`RecursosPage.jsx:9-14`) and Programacion (`ProgramacionPage.jsx:12-17`) define their **own inline** disclaimer with different wording instead of importing `resources/js/components/legal/AiDisclaimer.jsx`. Text drift = compliance risk. |
| **Modal a11y (focus trap / Esc)** | ⚠️ Docente modals lack the `useFocusTrap`/`useEscapeKey` the Oposición/secure modals use. |
| **Mobile responsiveness** | ⚠️ Mostly responsive (`sm:`/`lg:` grids) except Horario `min-w-[600px]` (line 161) and Meritos desglose pills (no `sm:` breakpoints). |

### UX/Consistency severity
- **HIGH:** AI disclaimer non-canonical/duplicated text (Recursos 9-14, Programacion 12-17) — legal/compliance drift; Horario mobile grid break (line 161).
- **MEDIUM:** no `font-heading` on any docente title; thin/inconsistent empty states; read-only generated output; no copy confirmation in Banco; modal a11y.
- **LOW:** ambiguous Horario session-note copy; unclear "Proyección" label; export-baremo discoverability; Asistente context hint.

---

# Remediation priority

1. **SEC-D1** widen PII regex (add `\d{8}[A-Z]`) + centralise & apply to all free-text AI inputs.
2. **SEC-D2** scope `asignatura_id`/`unidad_id` lookups in `generateExamen` (and dead `generateActividad`) to the caller.
3. **SEC-D3** add `throttle:ai-generate` to `programaciones/{id}/adaptar` (routes/api.php:329).
4. **SEC-D4** plan-gate the docente AI routes + enforce the daily cap in `DocenteAiService`.
5. **SEC-D5 / SEC-D6** ownership/`exists` checks for FK ids in `store()` and for `document_id`.
6. **UX-HIGH** replace inline disclaimers with `legal/AiDisclaimer.jsx`; fix Horario mobile grid.
7. **SEC-D8/D9** remove `puntos_calculados` from `$fillable`; replace raw-SQL concat with `increment()`.
8. **UX-MEDIUM** docente `shared.jsx` `SectionTitle` (`font-heading`) across pages; unify empty states; add modal a11y; copy-confirmation toast.
9. **SEC-D10** when exports are built, use signed, user-bound routes — never raw paths.

*Audit limited strictly to Sprint D (Modo Docente). Security findings verified by direct read of every controller + `DocenteAiService` + routes + migration defaults; UX/consistency findings cite exact file:line in the docente React pages.*
