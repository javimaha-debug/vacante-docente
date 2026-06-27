# VacanteDocente

Organizador de vacantes para la adjudicación docente de la **Comunitat Valenciana** (curso 2025).
Permite explorar, filtrar, priorizar (kanban + arrastrar/soltar) y exportar las vacantes de una
especialidad, además de calcular el tiempo de viaje desde tu domicilio a los centros seleccionados.

Construido con **Laravel 12** (API + SPA shell), **React 18 + Vite + Tailwind CSS v4** y **MySQL**.
Las llamadas a Google Maps se hacen **siempre desde el servidor**; la API key nunca llega al navegador.

---

## Stack

| Capa      | Tecnología                                                        |
| --------- | ----------------------------------------------------------------- |
| Backend   | Laravel 12, PHP 8.2+, MySQL                                        |
| Frontend  | React 18, Vite 7, Tailwind CSS v4, @tanstack/react-query, @dnd-kit |
| Mapas     | Google Geocoding API + Distance Matrix API (server-side)          |
| Auth      | Ninguna (fase 1). Socialite + Google scaffolded para fase 2       |

---

## Puesta en marcha

```bash
# 1. Dependencias
composer install
npm install

# 2. Entorno
cp .env.example .env
php artisan key:generate
#  → edita .env: credenciales MySQL y (opcional) GOOGLE_MAPS_API_KEY

# 3. Base de datos
mysql -uroot -e "CREATE DATABASE vacante_docente CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed       # crea esquema + siembra especialidades y 1036 vacantes

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

## Fase 2 — Google sign-in (scaffolded, desactivado)

- `laravel/socialite` instalado; bloque `google` en `config/services.php`; claves `GOOGLE_CLIENT_*`
  (comentadas) en `.env`.
- `App\Http\Controllers\Auth\GoogleAuthController` con `redirect()`/`callback()` listos (comentados).
- Rutas `/auth/google/*` comentadas en `routes/web.php`.

Pasos para activarlo documentados en la cabecera de `GoogleAuthController`.
