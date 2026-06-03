# morocco-law-search

Moroccan legal search application built with Node.js, Express, MySQL, React, and TypeScript.

## Features

- Article-level search across indexed Moroccan legal texts
- Official source links for each result
- Search suggestions powered by the law library
- English and Arabic translation actions with external fallback links
- React frontend with a blue and white Marokko Biz interface
- Chat-only floating legal assistant widget
- Optional live checks for new SGG Bulletin Officiel PDFs
- Optional Ollama-powered reasoning over retrieved law articles

## Tech Stack

- Node.js
- Express
- MySQL
- React
- TypeScript
- Vite

## Setup

1. Install dependencies:

```bash
npm install
```

2. Create a `.env` file in the project root using `.env.example`.

3. Create a MySQL database named `morocco_law_search`.

4. Build the frontend:

```bash
npm run build
```

5. Run the server:

```bash
node src/server.js
```

6. Open:

```text
http://localhost:3000
```

## Environment Variables

```env
DB_USER=root
DB_HOST=localhost
DB_NAME=morocco_law_search
DB_PASSWORD=
DB_PORT=3306
LAW_UPDATE_INTERVAL_HOURS=0
LAW_UPDATE_RUN_ON_START=false
BO_LOOKAHEAD=80
BO_BACKFILL=24
BO_REQUEST_TIMEOUT_MS=8000
BO_DISCOVERY_CONCURRENCY=8
BO_REIMPORT_EXISTING=false
BO_REIMPORT_RECENT=0
AI_PROVIDER=none
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=qwen3:8b
AI_PLANNER_TIMEOUT_MS=12000
AI_ANSWER_TIMEOUT_MS=30000
```

## Import Scripts

```bash
npm run import:real-estate
npm run import:other-laws
npm run import:pending-laws
npm run update:official-bulletins
```

`update:official-bulletins` checks official SGG Bulletin Officiel PDF URLs, skips already indexed bulletin numbers, and imports newly discovered bulletins into the local database.

To keep the server checking automatically, set `LAW_UPDATE_INTERVAL_HOURS` to a positive number. Set `LAW_UPDATE_RUN_ON_START=true` to run one check shortly after server startup.

## Local AI Reasoning

The app can use a local Ollama model as the reasoning layer. The flow is:

```text
user question -> local law search -> retrieved articles -> Ollama reasoning answer with citations
```

Install Ollama on the machine that runs the backend, pull a model such as `qwen3:8b`, then set:

```env
AI_PROVIDER=ollama
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=qwen3:8b
```

If Ollama is not enabled or is unavailable, the chatbot falls back to source-based answers from the database.

## Frontend Development

```bash
npm run frontend:dev
```

The Vite dev server proxies API requests to `http://localhost:3000`.

## Notes

- Do not commit `.env`
- Do not commit `node_modules`
- Database dumps should be shared separately unless intentionally versioned
