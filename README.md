# Marokko Biz Law Search

Laravel rebuild of the Moroccan law search app. The original Node.js project is not required at runtime once this app has its own database imported.

## Local Run

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8010
```

Open:

- `http://127.0.0.1:8010/` for the Laravel-native search UI.
- `http://127.0.0.1:8010/app` for the copied compiled React UI.
- `http://127.0.0.1:8010/api/laws/search?q=Code%20du%20travail` for the API.

## Database Import

Import from the legacy Node/MySQL database:

```bash
php artisan laws:import-legacy
```

Required legacy env values:

```env
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=morocco_law_search
LEGACY_DB_USERNAME=root
LEGACY_DB_PASSWORD=
```

Import one legal PDF directly:

```bash
php artisan laws:import-pdf "https://example.test/source.pdf" \
  --document-title="Example Legal Text" \
  --law-reference="Loi 00-00" \
  --category="example" \
  --tag=example
```

Discover/import recent Official Bulletin PDFs and sync them into the versioned corpus:

```bash
php artisan corpus:update-official-sources --source=all
```

Supported automatic source updates right now:

- `official-bulletins`: SGG Bulletin Officiel PDFs from `https://www.sgg.gov.ma/BO/FR/2873`

The old flat-table-only command still exists for compatibility:

```bash
php artisan laws:update-official-bulletins
```

## Production Scheduler

Laravel schedules do not run by themselves. The app defines the scheduled task in `routes/console.php`, but production must run one scheduler process.

Recommended production cron entry:

```bash
* * * * * cd /absolute/path/to/Marrokobiz\ law\ search && /usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

That one-minute cron calls Laravel, and Laravel decides whether the daily job is due.

Current app schedule:

- Command: `corpus:update-official-sources --source=all`
- Time: daily at `03:00`
- Timezone: `Africa/Casablanca`
- Locking: `withoutOverlapping()`

For local development only, you can keep the scheduler running in a terminal:

```bash
php artisan schedule:work
```

For a VPS with Supervisor, use `deploy/supervisor-scheduler.conf.example`. For cPanel/shared hosting, use `deploy/cron.example`.

## API Routes

- `GET /api/corpus/status`
- `GET /api/laws/overview`
- `GET /api/laws/suggestions?q=...`
- `GET /api/laws/search?q=...`
- `POST /api/laws/chat`
- `GET /api/laws/{law}/translate?target=en`

## AI And Translation

AI reasoning is optional. By default:

```env
AI_PROVIDER=none
```

To use local Ollama:

```env
AI_PROVIDER=ollama
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=qwen3:8b
```

Inline translation uses public free providers and caches successful translations in `law_translations`. If providers are unavailable, the API returns a Google Translate fallback URL.

## Production Deploy Notes

Use PHP 8.2+ and set the web document root to `public`.

Recommended deploy commands:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

Production env basics:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

Make sure `storage/` and `bootstrap/cache/` are writable by the PHP process.
Make sure a production scheduler runner is configured with cron or Supervisor; otherwise official-source updates will not execute automatically.

## Verification

```bash
php artisan test
```

Current local rebuild verification: `25 passed (119 assertions)`.
