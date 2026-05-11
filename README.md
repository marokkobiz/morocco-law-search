<<<<<<< HEAD
# morocco-law-search

Moroccan legal search application built with Node.js, Express, and MySQL.

## Features

- Search Moroccan laws at article level
- Source links to official legal documents
- Coverage across real estate, commercial, civil, labor, family, banking, consumer, criminal, and investment law
- English and Arabic translation support with fallback links when inline translation is unavailable

## Tech Stack

- Node.js
- Express
- MySQL

## Project Structure

```text
src/
  controllers/
  data/
  db/
  models/
  public/
  routes/
  scripts/
  services/
```

## Setup

1. Install dependencies:

```bash
npm install
```

2. Create a `.env` file in the project root using `.env.example`.

3. Create a MySQL database named `morocco_law_search`.

4. Run the server:

```bash
node src/server.js
```

5. Open:

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
```

## Import Scripts

```bash
npm run import:real-estate
npm run import:other-laws
```

## Notes

- Do not commit `.env`
- Do not commit `node_modules`
- Database dumps should be shared separately unless intentionally versioned
=======
# morocco-law-search
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
