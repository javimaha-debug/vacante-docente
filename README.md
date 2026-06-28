# VacanteDocente

Organizador de vacantes para la adjudicación docente de la **Comunitat Valenciana**.
Permite explorar, filtrar, priorizar (kanban + arrastrar/soltar) y exportar las vacantes de una
especialidad, calcular el tiempo de viaje desde tu domicilio a los centros, consultar tu posición
en bolsa sobre el último listado, y recibir avisos cuando un listado se actualiza.

Construido con **Laravel 12** (API + SPA shell), **React 18 + Vite + Tailwind CSS v4** y **PostgreSQL**.
Las llamadas a Google Maps se hacen **siempre desde el servidor**; la API key nunca llega al navegador.

> **Despliegue en producción:** ver [`DEPLOYMENT.md`](DEPLOYMENT.md) — incluye auth, notificaciones,
> push web, monitor GVA con importación automática y todas las variables de entorno.

---

## Funcionalidades

- **Explorador** con filtros inteligentes combinables (provincia, tipo y características de centro,
  requisit lingüístic, itinerant, observaciones, rango de distancia, tiempo de viaje, estado), kanban
  y vista lista con arrastrar/soltar, y exportación.
- **Distancias** (coche/público/a pie, ida y vuelta) desde tu domicilio, vía Google Maps server-side.
- **Mi posición en bolsa** calculada sobre el último listado importado, con su fecha.
- **Detección de cambios**: al reimportar un listado se resaltan las plazas/participantes nuevos o
  modificados y se avisa por **campana in-app, email y push web**.
- **Monitor GVA** diario que detecta y **auto-importa** los listados publicados, con vista de
  administración para revisar/reimportar.
- **Acceso** con email/contraseña, Google y (opcional) Microsoft.

---

## Stack

| Capa      | Tecnología                                                        |
| --------- | ----------------------------------------------------------------- |
| Backend   | Laravel 12, PHP 8.2+, PostgreSQL (SQLite en tests)                |
| Frontend  | React 18, Vite 7, Tailwind CSS v4, @tanstack/react-query, @dnd-kit |
| Mapas     | Google Geocoding + Distance Matrix + Places (server-side)         |
| Auth      | Sanctum (tokens) · email/contraseña + Socialite (Google, Microsoft) |
| Avisos    | Notificaciones Laravel: database + mail + web push (minishlink/web-push) |

---

## Puesta en marcha

```bash
# 1. Dependencias
composer install
npm install

# 2. Entorno
cp .env.example .env
php artisan key:generate
#  → edita .env: credenciales PostgreSQL y (opcional) GOOGLE_MAPS_API_KEY

# 3. Base de datos
createdb vacante_docente          # PostgreSQL (en tests se usa SQLite)
php artisan migrate --seed       # crea esquema + siembra especialidades y vacantes

# 4. Frontend
npm run build                    # o `npm run dev` para HMR

# 5. Servir
php artisan serve                # http://localhost:8000
```

> `composer run dev` levanta a la vez `serve`, `queue`, `pail` y `vite`.

### Google Maps (opcional pero necesario para distancias)

Geocodificación y cálculo de distancias requieren una clave con **Geocoding API** y
**Distance Matrix API** habilitadas:

```env
GOOGLE_MAPS_API_KEY=tu_clave_aqui
```

Sin clave, la app funciona con normalidad salvo el panel de distancias, que responde `503`
y muestra un aviso. La clave solo se usa en `App\Services\GoogleMapsService` (servidor).

---

## API (`/api/v1`)

| Método | Ruta                                          | Descripción                                            |
| ------ | --------------------------------------------- | ------------------------------------------------------ |
| GET    | `/specialties`                                | Especialidades agrupadas por `maestros/secundaria/fp`  |
| GET    | `/vacancies`                                  | Vacantes paginadas (filtros + distancias si hay token) |
| POST   | `/user-lists`                                 | Crea/recupera la lista de `session_token + specialty`  |
| PATCH  | `/user-lists/{id}`                            | Actualiza domicilio (address/lat/lng)                  |
| GET    | `/user-lists/{id}/preferences`                | Preferencias (seleccionadas → neutras → descartadas)   |
| PUT    | `/user-lists/{id}/preferences/bulk`           | Upsert masivo en una transacción                       |
| POST   | `/user-lists/{id}/geocode`                    | Geocodifica una dirección (rate limit 20/min/IP)       |
| POST   | `/user-lists/{id}/calculate-distances`        | Distancias de las seleccionadas (rate limit 5/min/IP)  |

`mode` en `calculate-distances` admite `driving`, `transit`, `walking` o `all`. Para `driving`
se usa `departure_time = próximo lunes 08:00` (tráfico). Los resultados se cachean en
`distance_cache` por `vacancy_id + coords redondeadas a 4 decimales + mode`.

---

## Estructura del frontend

```
resources/js/
  app.jsx                 # entry: App + Organizer (orquestación de estado)
  components/             # Layout, SpecialtySelector, VacancyCard, FiltersPanel,
                          # HomeAddressPanel, KanbanBoard, SortableList, ExportPanel
  hooks/                  # useUserList, useVacancies, useDistances
  lib/                    # api.js (axios), session.js (localStorage)
```

Flujo: al cargar se lee `localStorage` (`session_token`, `specialty_id`). Sin especialidad →
`SpecialtySelector` a pantalla completa; con especialidad → organizador (kanban / lista).

---

## Decisiones de diseño / notas sobre el esquema

- **`specialties`**: los códigos se solapan entre `maestros` y `secundaria` (120–126), por lo que
  la unicidad es **`(code, education_level)`** en lugar de solo `code`. La especialidad principal con
  datos es la `218` (Orientación Educativa, secundaria) — 1036 vacantes.
- **`user_lists`**: la unicidad efectiva es **`(session_token, specialty_id)`** (más un índice en
  `session_token`), para soportar el upsert por sesión + especialidad descrito en la API.
- **Distancias a centros**: como `vacancies` no almacena coordenadas, el destino del Distance Matrix
  se construye a partir de `centro_nombre + localidad + provincia + España` (Google acepta direcciones).

---

## Autenticación

- **Email/contraseña**: `POST /api/v1/auth/register` y `/auth/login` (rate limited) devuelven un
  token Sanctum. Funciona sin configuración.
- **Social (Socialite)**: `GET /auth/{provider}` → callback en `/auth/{provider}/callback`. Cada
  proveedor (`google`, `microsoft`) se activa solo cuando tiene credenciales; `GET /api/v1/auth/providers`
  indica al SPA qué botones mostrar.
- El token se entrega al SPA vía `/dashboard?token=…` (OAuth) o en el cuerpo JSON (email/contraseña)
  y se guarda en `localStorage`.

Configuración completa (Google, Microsoft, push web, monitor GVA): ver [`DEPLOYMENT.md`](DEPLOYMENT.md).
