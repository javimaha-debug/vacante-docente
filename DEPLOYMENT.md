# Despliegue — VacanteDocente

Guía para poner la aplicación en producción (`vacantes.movvos.com`). El código
está completo; lo que sigue es configuración de servidor, datos y procesos.

## 1. Requisitos del servidor

| Componente | Versión / nota |
|---|---|
| PHP | 8.2+ con extensiones `pdo_pgsql`, `mbstring`, `intl`, `curl` |
| Composer | 2.x |
| Node.js | 20+ (solo para `npm run build`) |
| PostgreSQL | 14+ (la app usa `jsonb`; las migraciones son PG-compatible) |
| poppler-utils | proporciona `pdftotext`, necesario para los comandos de import de PDF |
| Servidor web | Nginx + PHP-FPM (recomendado) |

```bash
# Debian/Ubuntu
sudo apt-get update && sudo apt-get install -y poppler-utils
pdftotext -v   # comprobar
```

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

# Google OAuth (Socialite)
GOOGLE_CLIENT_ID=********
GOOGLE_CLIENT_SECRET=********
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

# Email (notificaciones del tablón) — usa el driver que tengas (SMTP/SES/...)
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@movvos.com"
MAIL_FROM_NAME="${APP_NAME}"

# Colas (los emails del tablón se encolan)
QUEUE_CONNECTION=database     # o redis
```

> La `GOOGLE_MAPS_API_KEY` debe tener habilitadas **Geocoding API**,
> **Distance Matrix API** y **Places API**.
> La **Places API** es la que da el autocompletado de dirección útil con texto
> parcial; si no está habilitada, el buscador cae automáticamente al Geocoding
> (que solo encuentra direcciones ya casi completas).

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
php artisan db:seed --class=VacancySeeder      # 1036 vacantes Orientación 2025/2026

# Procesos del curso actual (2026-2027)
php artisan procesos:create-current

# Cachés de producción
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 4. Google OAuth

En [Google Cloud Console](https://console.cloud.google.com/) → *APIs & Services*
→ *Credentials* → OAuth 2.0 Client ID (tipo *Web application*):

- **Authorized redirect URI:** `https://vacantes.movvos.com/auth/google/callback`
  (debe coincidir exactamente con `GOOGLE_REDIRECT_URI`).
- Copia *Client ID* y *Client Secret* al `.env`.

El flujo: `/auth/google` → consentimiento → `/auth/google/callback` crea/actualiza
el usuario, emite un token Sanctum y redirige a `/dashboard?token=…` (el SPA lo
guarda en `localStorage`).

## 5. Worker de colas (emails del tablón)

Los mailables (`TablonContactoMail`, `TablonRespuestaMail`) se envían con `queue`.
Hace falta un worker vivo. Con `QUEUE_CONNECTION=database`:

```bash
php artisan queue:table && php artisan migrate --force   # tabla jobs (si no existe)
```

Supervisor (`/etc/supervisor/conf.d/vacante-worker.conf`):

```ini
[program:vacante-worker]
command=php /var/www/vacante-docente/artisan queue:work --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/vacante-docente/storage/logs/worker.log
```

## 6. Scheduler (monitor GVA diario)

`MonitorGvaJob` está programado en `routes/console.php` para las **08:00
Europe/Madrid**. Necesita el cron de Laravel:

```cron
* * * * * cd /var/www/vacante-docente && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Datos: importaciones

Coloca los ficheros en `storage/app/private/pdfs/gva/` (el disco `local` de
Laravel 12 tiene su raíz en `storage/app/private`; está fuera de git).

```bash
# Vacantes de suprimidos 2026-2027 (PDFs Secundaria + Mestres)
#   storage/app/private/pdfs/gva/suprimits-secundaria-2026.pdf
#   storage/app/private/pdfs/gva/suprimits-primaria-2026.pdf
php artisan vacantes:import-suprimidos-2026

# Directorio de centros — listados ANPE (7 PDFs CENTRES_*_2026-27.pdf en el
# mismo directorio): UECO, Educació Especial, Caràcter Singular, FPA, CRA,
# Penitenciaris, Jornada Continuada. Marca tipo y características.
php artisan centros:import-anpe

# (Opcional) Directorio de centros desde CSV de la GVA o fallback local
#   storage/app/private/pdfs/gva/centros.csv
php artisan centros:import

# Lista de participantes de un proceso
php artisan participantes:import-pdf storage/app/private/pdfs/gva/participantes.pdf <proceso_id>
```

> Los parsers de PDF requieren `pdftotext` (poppler-utils). Suprimidos y los
> listados ANPE están validados contra los PDF reales 2026-2027; al importar
> nuevas ediciones, revisa el conteo por especialidad/característica y ajusta
> regex/alias si el formato cambia. La resolución de especialidad en suprimidos
> es **por nombre** (los códigos de sección GVA no coinciden con los internos).

## 8. Comprobaciones post-despliegue

```bash
php artisan route:list | grep api/v1   # rutas cargadas
php artisan schedule:list              # monitor-gva a las 08:00
php artisan test                       # suite (si se despliega con dev deps)
```

- `GET /api/v1/specialties`, `/api/v1/vacancies`, `/api/v1/procesos` → 200 público.
- Login con Google → dashboard.
- Perfil → autocompletado de dirección (verifica `GOOGLE_MAPS_API_KEY`).
- Explorador → "Calcular distancias" sobre la lista (verifica Distance Matrix).
