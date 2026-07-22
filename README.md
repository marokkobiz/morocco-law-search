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

```

---

## ⚙️ How the Pipeline Works

NB: Take a look at the requirements.txt file to make sure ALL pre-requisites are INSTALLED

The project follows a **5-step data pipeline** from initial scraping to web display:

```
[1. Scrape & Download] ➔ [2. Text Extraction]➔ [3. Chunking]  ➔ [4. Parsing & Categorization]  ➔ [5. Search Interface]

```

1. **Scraping (`marocloi/` & `scrapy.cfg`)**
* Automatically crawls official legal portals to download Moroccan laws into the `downloaded_laws/` folder.


2. **Extraction (`extracted_laws/`)**
* Converts downloaded documents into readable raw text format.


3. **Chunking (`Chunker.py`)**
* Breaks long legal texts into smaller, semantically coherent segments suitable for accurate searching and indexing.

4.   **Parsing & Categorization (`Parser.py` & `Categorizer.py`)**
* Extracts key metadata (articles, dates, law numbers) and organizes them into structured JSON files inside `json_laws/`.


5. **Web Interface (`Website.py`)**
* Displays the search dashboard where users can query Moroccan laws and view results.



---

## 🚀 How to Run the Project

### Option 1: Run the Full Pipeline (Automated)

To execute the entire data pipeline and launch the application, run the master orchestrator script:

```bash
python run_everything.py

```

### Option 2: Run the Web App Only

If the law data is already processed into `json_laws/`, you can directly launch the web dashboard:

```bash
python Website.py

```

### Option 3: Run with Docker

To run the application inside a containerized environment:

```bash
docker-compose up --build

```

---

## ⚙️ Environment Setup

1. Copy `.env.example` to create your own configuration file:
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

## License

This project is open-sourced under the MIT license.
