# Morocco Law Search

Morocco Law Search is a Laravel 12 application with Vite for frontend assets, SQLite for local development, and database-backed queues.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js 18 or newer
- npm
- SQLite enabled in PHP

## Project Setup

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Install frontend dependencies:

   ```bash
   npm install
   ```

3. Create your environment file:

   ```bash
   # macOS / Linux
   cp .env.example .env

   # Windows PowerShell
   Copy-Item .env.example .env
   ```

4. Update `.env` for your machine.

   Recommended values for local development:

   ```env
   APP_NAME="Morocco Law Search"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   DB_CONNECTION=sqlite
   QUEUE_CONNECTION=database
   SESSION_DRIVER=database
   CACHE_STORE=database
   ```

   If you keep spaces in `APP_NAME`, wrap the value in quotes.

5. Create the SQLite database file if it does not exist yet:

   ```bash
   # macOS / Linux
   touch database/database.sqlite

   # Windows PowerShell
   New-Item -ItemType File -Force database\database.sqlite
   ```

6. Generate the application key:

   ```bash
   php artisan key:generate
   ```

7. Run the migrations:

   ```bash
   php artisan migrate
   ```

## Running the App

### Full development mode

Run the Laravel server and Vite together with:

```bash
composer run dev
```

This starts:

- Laravel server
- Queue listener
- Vite dev server

### Run each service manually

If you prefer to start them one by one:

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
npm run dev
```

### Build assets for production

```bash
npm run build
```

## Notes

- The default route currently returns the `welcome` view from `routes/web.php`.
- `composer run dev` is configured to work on Windows by skipping Laravel Pail, which requires the `pcntl` extension.
- If you want log tailing on a system that supports `pcntl`, run it separately with:

  ```bash
  php artisan pail --timeout=0
  ```

## Testing

Run the test suite with:

```bash
composer test
```

## Useful Commands

- `php artisan route:list`
- `php artisan migrate:fresh`
- `php artisan config:clear`
- `php artisan optimize:clear`
- `php artisan corpus:embed-chunks` — generate embeddings and sync them to Qdrant
- `php artisan corpus:sync-qdrant` — backfill existing SQL embeddings into Qdrant

## Qdrant Vector Search

Semantic search uses [Qdrant](https://qdrant.tech/) for fast vector retrieval. SQL remains the source of truth for corpus metadata; Qdrant stores chunk embeddings and search filters.

1. Start Qdrant locally:

   ```bash
   docker compose up -d qdrant
   ```

2. Add these values to `.env`:

   ```env
   QDRANT_ENABLED=true
   QDRANT_URL=http://127.0.0.1:6333
   QDRANT_COLLECTION=legal_chunks
   QDRANT_VECTOR_SIZE=768

   LEGAL_SEMANTIC_SEARCH_ENABLED=true
   OLLAMA_BASE_URL=http://127.0.0.1:11434
   OLLAMA_EMBEDDING_MODEL=nomic-embed-text
   ```

3. Embed the corpus and sync vectors:

   ```bash
   php artisan corpus:embed-chunks
   php artisan corpus:sync-qdrant
   ```

If Qdrant is unavailable, search automatically falls back to the previous SQL + PHP cosine similarity path.

## License

This project is open-sourced under the MIT license.
