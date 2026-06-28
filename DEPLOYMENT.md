# Despliegue — VacanteDocente

Guía para poner la aplicación en producción (`vacantes.movvos.com`, Laravel
Forge). El código está completo; lo que sigue es configuración de servidor,
datos y procesos.

> **Resumen rápido de un despliegue** (cuando ya está todo configurado):
> ```bash
> cd ~/vacantes.movvos.com/current
> git pull origin main
> composer install --no-dev --optimize-autoloader
> php artisan migrate --force
> npm ci && npm run build
> php artisan optimize:clear && php artisan config:cache && php artisan route:cache
> ```

---

## 1. Requisitos del servidor

| Componente | Versión / nota |
|---|---|
| PHP | 8.2+ con extensiones `pdo_pgsql`, `mbstring`, `intl`, `curl`, `gmp` o `bcmath` (web push) |
| Composer | 2.x |
| Node.js | 20+ (solo para `npm run build`) |
| PostgreSQL | 14+ (la app usa `jsonb`; las migraciones son PG-compatible) |
| poppler-utils | proporciona `pdftotext`, necesario para importar PDFs (vacantes y participantes) |
| Servidor web | Nginx + PHP-FPM (recomendado), **HTTPS obligatorio** (push web y OAuth lo exigen) |

```bash
# Debian/Ubuntu
sudo apt-get update && sudo apt-get install -y poppler-utils
pdftotext -v   # comprobar
```

---

## 2. Variables de entorno (`.env`)

Partiendo de `.env.example`:

```dotenv
APP_NAME=VacanteDocente
APP_ENV=production
APP_KEY=                      # php artisan key:generate
APP_DEBUG=false
APP_URL=https://vacantes.movvos.com

# Base de datos (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vacante_docente
DB_USERNAME=vacante
DB_PASSWORD=********

# Google Maps (server-side; NUNCA exponer al frontend)
GOOGLE_MAPS_API_KEY=********

# --- Autenticación ---------------------------------------------------------
# Email/contraseña funciona sin configurar nada.

# Google OAuth (Socialite)
GOOGLE_CLIENT_ID=********
GOOGLE_CLIENT_SECRET=********
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

# Microsoft OAuth (opcional; ver §5). El botón aparece solo si está configurado.
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
MICROSOFT_TENANT=common

# --- Notificaciones --------------------------------------------------------
# Email (notificaciones del tablón y avisos de listados) — driver SMTP/SES/...
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@movvos.com"
MAIL_FROM_NAME="${APP_NAME}"

# Web Push (opcional; ver §6). Genera el par con: php artisan webpush:vapid
VAPID_SUBJECT="mailto:admin@movvos.com"
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=

# --- GVA ------------------------------------------------------------------
# Importación automática de listados detectados por el monitor (ver §8).
GVA_AUTO_IMPORT=true

# Colas (emails, monitor GVA, notificaciones y auto-import se encolan)
QUEUE_CONNECTION=database     # o redis
```

> La `GOOGLE_MAPS_API_KEY` debe tener habilitadas **Geocoding API**,
> **Distance Matrix API** y **Places API**.
> La **Places API** es la que da el autocompletado de dirección útil con texto
> parcial; si no está habilitada, el buscador cae automáticamente al Geocoding.

---

## 3. Build e instalación

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate            # si APP_KEY está vacío
npm ci && npm run build             # genera public/build (Vite)

php artisan migrate --force

# Catálogo base (idempotente)
php artisan db:seed --class=CcaaSeeder
php artisan db:seed --class=ColectivoSeeder
php artisan db:seed --class=SpecialtySeeder
php artisan db:seed --class=VacancySeeder      # vacantes Orientación 2025/2026

# Procesos del curso actual (2026-2027)
php artisan procesos:create-current

# Cachés de producción
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Persistencia entre despliegues (Forge)

Asegúrate de que `storage/` es un enlace **compartido** entre releases (Forge lo
hace por defecto). Si no, los PDFs importados se pierden en cada deploy. Los
datos en PostgreSQL sí persisten.

---

## 4. Autenticación: email/contraseña + Google

Hay tres formas de entrar; la pantalla de acceso muestra las disponibles
automáticamente (consulta `GET /api/v1/auth/providers`).

- **Email + contraseña** (`/api/v1/auth/register`, `/api/v1/auth/login`):
  funciona sin configurar nada. Devuelve un token Sanctum que el SPA guarda en
  `localStorage`.
- **Google** (Socialite): configura las credenciales en el `.env`.
- **Microsoft**: opcional, ver §5.

### Google OAuth

En [Google Cloud Console](https://console.cloud.google.com/) → *APIs & Services*
→ *Credentials* → OAuth 2.0 Client ID (tipo *Web application*):

- **Authorized redirect URI:** `https://vacantes.movvos.com/auth/google/callback`
  (debe coincidir exactamente con `GOOGLE_REDIRECT_URI`).
- Copia *Client ID* y *Client Secret* al `.env`.

El flujo: `/auth/google` → consentimiento → `/auth/google/callback` crea/actualiza
el usuario, emite un token Sanctum y redirige a `/dashboard?token=…`.

---

## 5. Microsoft OAuth (opcional)

El código ya registra el provider de Socialite de forma segura (solo si el
paquete está instalado). Para activarlo:

```bash
composer require socialite-providers/microsoft
```

En **Azure AD** → *App registrations* → tu app → *Authentication* → *Redirect
URIs* añade `https://vacantes.movvos.com/auth/microsoft/callback`, y crea un
*client secret* en *Certificates & secrets*. Luego en `.env`:

```dotenv
MICROSOFT_CLIENT_ID=...
MICROSOFT_CLIENT_SECRET=...
MICROSOFT_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
MICROSOFT_TENANT=common        # o el tenant id de tu organización
```

```bash
php artisan config:cache
```

El botón «Continuar con Microsoft» aparece solo en cuanto `MICROSOFT_CLIENT_ID`
está definido. (Apple se retiró a propósito.)

---

## 6. Notificaciones (in-app, email y push web)

Cuando se actualiza un listado se avisa a los docentes afectados por tres vías.

- **In-app**: campana en la cabecera (tabla `notifications`). Sin configuración.
- **Email**: usa el mailer de §2. Respeta la preferencia `notificaciones_email`
  de cada usuario.
- **Push web** (opcional): notificaciones del navegador/móvil. Requiere claves
  VAPID y **HTTPS**.

### Activar el push web

```bash
composer install            # asegura minishlink/web-push (ya en composer.json)
php artisan webpush:vapid    # imprime las 3 líneas VAPID_*
# copia VAPID_SUBJECT / VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY al .env
php artisan config:cache
```

Cada usuario activa el push por dispositivo desde **Mi Perfil** (el interruptor
solo aparece si el navegador lo soporta y hay VAPID configurado). El service
worker es `public/sw.js`. Sin VAPID, los avisos siguen llegando por campana y
email.

---

## 7. Worker de colas + scheduler

Tanto los emails como el **monitor GVA**, las **notificaciones** y la
**importación automática** se ejecutan en cola y/o programados. Hacen falta dos
procesos vivos.

### Worker de colas (Forge: *Daemons*; o Supervisor)

```ini
[program:vacante-worker]
command=php /home/forge/vacantes.movvos.com/current/artisan queue:work --tries=3 --timeout=120
autostart=true
autorestart=true
user=forge
numprocs=1
redirect_stderr=true
stdout_logfile=/home/forge/vacantes.movvos.com/current/storage/logs/worker.log
```

Con `QUEUE_CONNECTION=database`, la tabla `jobs` ya la crean las migraciones.

### Scheduler (Forge: *Scheduler*; o cron)

`MonitorGvaJob` está programado en `routes/console.php` para las **08:00
Europe/Madrid**. Necesita el cron de Laravel:

```cron
* * * * * cd /home/forge/vacantes.movvos.com/current && php artisan schedule:run >> /dev/null 2>&1
```

Comprobación: `php artisan schedule:list` debe mostrar `monitor-gva`.

---

## 8. GVA: detección, importación automática y revisión

> Diagrama del flujo completo y referencia de la API en [`docs/API.md`](docs/API.md).

### Flujo automático

1. `MonitorGvaJob` (diario) lee el RSS del DOGV y rastrea la página de
   adjudicaciones; guarda novedades en `gva_noticias`.
2. Si `GVA_AUTO_IMPORT=true`, descarga los PDFs de listado detectados y los
   **importa automáticamente**, deduciendo el proceso destino del nombre del
   fichero (tipo · cuerpo · colectivo · año).
3. Cada import dispara la **detección de cambios** (nuevas/modificadas/
   eliminadas) y notifica a los docentes afectados.
4. Se **avisa a los administradores** (campana + email) con el resultado.

Para volver a solo-detección (sin importar): `GVA_AUTO_IMPORT=false` + `config:cache`.

### Vista de administración

Menú **«Importaciones»** (visible solo para admins) en
`/dashboard/admin/importaciones`: lista los PDFs detectados con su estado
(Importado / Requiere proceso / Error), resumen de cambios y enlace al original.
Permite **reimportar** o **importar a mano** en un proceso elegido cuando la
heurística no pudo asociarlo.

Marcar un usuario como administrador:

```bash
php artisan tinker --execute="\App\Models\User::where('email','admin@movvos.com')->update(['is_admin'=>true]);"
```

### Importación manual (siempre disponible)

Coloca los ficheros en `storage/app/private/pdfs/gva/` (raíz del disco `local`
en Laravel 12; fuera de git).

```bash
# Vacantes de suprimidos 2026-2027 (PDFs Secundaria + Mestres)
php artisan vacantes:import-suprimidos-2026

# Directorio de centros — listados ANPE (7 PDFs CENTRES_*_2026-27.pdf):
# UECO, Educació Especial, Caràcter Singular, FPA, CRA, Penitenciaris, Jornada
# Continuada. Marca tipo y características (alimentan el filtro del explorador).
php artisan centros:import-anpe

# (Opcional) Enriquecer centros con la API REST de la GVA + geocodificación
php artisan centros:enrich

# Vacantes / participantes de un proceso (re-ejecutable; detecta cambios)
php artisan vacantes:import-pdf storage/app/private/pdfs/gva/vacantes.pdf <proceso_id>
php artisan participantes:import-pdf storage/app/private/pdfs/gva/participantes.pdf <proceso_id>
```

> Los parsers requieren `pdftotext`. La **detección de cambios** (resaltado +
> avisos) aparece a partir de la **segunda** importación de cada listado (la
> primera no tiene con qué comparar).

### Adjudicaciones contínues (semanales)

Las adjudicaciones contínues (martes/jueves) se publican en la GVA como
`YYMMDD_lis_sec.pdf` / `YYMMDD_lis_mae.pdf`. Se importan conservando el
histórico por fecha (cada tanda se guarda; no se sobreescribe):

```bash
# Acepta URL directa; deduce fecha (del título "DIA dd/mm/aaaa" o del nombre)
# y cuerpo (de _sec/_mae). Re-ejecutable por (fecha, cuerpo).
php artisan adjudicaciones:import-continua "https://ceice.gva.es/documents/162909733/410063968/260602_lis_sec.pdf"
php artisan adjudicaciones:import-continua ruta/260602_lis_mae.pdf --fecha=2026-06-02 --cuerpo=MAESTROS
```

Cada docente ve su histórico semanal en el panel («Mis adjudicaciones
semanales»), emparejado por nombre GVA.

### Cargar histórico de años anteriores

Para tener histórico de vacantes y adjudicaciones de cursos pasados, crea los
procesos de cada año (como `cerrado`) y luego importa sus PDFs:

```bash
# 1) Crea los 6 procesos (colectivos × cuerpos) de un curso pasado
php artisan procesos:create 2024                 # curso 2024-2025, estado cerrado
php artisan procesos:create 2023 --curso=2023-24 # etiqueta de curso personalizada

# 2) Localiza los ids (php artisan tinker / GET /api/v1/procesos) e importa
php artisan vacantes:import-pdf      ruta/vacants-2024.pdf       <proceso_id>
php artisan participantes:import-pdf ruta/adjudicacions-2024.pdf <proceso_id>
```

- La importación de **participantes** rellena el histórico del usuario
  (`user_historial`) por año, emparejando por **nombre GVA**; los **Adjudicat**
  guardan centro, lloc y jornada (el centro se resuelve por su código, así que
  conviene tener el directorio cargado con `centros:import-anpe`).
- Como es la **primera** importación de cada proceso histórico, **no** dispara
  notificaciones ni marca cambios (es_primera).

---

## 9. Funcionalidades del explorador y el perfil

- **Filtros inteligentes** (cliente, combinables con AND, en tiempo real):
  búsqueda, provincia, tipo de centro, características (CRA, singular, CEE, FPA,
  CIPFP, UECO, penitenciari, jornada contínua), requisit lingüístic / itinerant
  (tri-estado), observaciones, rango de distancia (km), tiempo máximo por modo,
  y estado en mi lista. Contador en vivo, «Limpiar filtros» y persistencia en
  `localStorage`. En móvil, botón «Filtrar» con badge.
- **Estados de vacante**: Sin revisar / En mi lista / A revisar / Descartada
  (kanban + vista lista, con arrastrar/soltar).
- **Especialidades**: se gestionan dentro de **Mi Perfil**. La posición en bolsa
  se calcula sobre el **último listado** importado y muestra su fecha.

---

## 10. Migraciones relevantes (referencia)

Todas se aplican con `php artisan migrate --force`. Tablas/columnas añadidas por
las últimas funcionalidades:

| Migración | Aporta |
|---|---|
| `add_revisar_status_to_preferences` | estado «A revisar» (quita el CHECK enum en PG) |
| `add_cambios_to_vacancies` | `cambio`/`cambio_en` en `vacancies` + tabla `proceso_importaciones` |
| `add_cambios_to_participantes` | `cambio`/`cambio_en` + tabla `participante_importaciones` |
| `create_notifications_table` | bandeja in-app |
| `create_push_subscriptions_table` | suscripciones de push web |
| `add_import_tracking_to_gva_noticias` | `importado_en`, `import_estado`, `import_resumen`, `proceso_id` |

---

## 11. Comprobaciones post-despliegue

```bash
php artisan route:list | grep api/v1   # rutas cargadas
php artisan schedule:list              # monitor-gva a las 08:00
php artisan queue:work --once          # el worker procesa trabajos
php artisan test                       # suite (si se despliega con dev deps)
```

- `GET /api/v1/specialties`, `/api/v1/vacancies`, `/api/v1/procesos` → 200 público.
- `GET /api/v1/auth/providers` → lista los métodos de login activos.
- Registro/login con email, y login con Google (y Microsoft si está configurado).
- Perfil → autocompletado de dirección (verifica `GOOGLE_MAPS_API_KEY`).
- Explorador → «Calcular distancias» + filtros (verifica Distance Matrix).
- Mi Perfil → interruptor de push (si hay VAPID) y gestión de especialidades.
- Menú «Importaciones» visible para el usuario admin.
