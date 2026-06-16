# Morocco Law Search (MarocLoi) — Project Context

## What It Is
A full-stack web application for searching, browsing, translating, and exploring Moroccan legal texts. It ingests legal sources from the Moroccan government's official portal (SGG — Secretariat General du Gouvernement), processes them into structured documents/articles/chunks, and provides a searchable workspace with semantic AI capabilities. Targets legal professionals (lawyers, judges, notaries) who need to look up Moroccan laws across English, French, and Arabic.

## Tech Stack
- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** Blade templating + vanilla JavaScript (no React/Vue), Vite 7, Tailwind CSS 4
- **Database:** SQLite (dev) / MySQL (prod)
- **AI:** Local Ollama — `qwen3:8b` for chat, `nomic-embed-text` for embeddings
- **Translation:** Google Translate public API + MyMemory API (free tiers)
- **Payments:** Stripe (Checkout Sessions + Webhooks)
- **Queue:** Database-backed Laravel queue

## Data Model Hierarchy
```
LegalSource (e.g. "Bulletin officiel n° 5210")
  └── LegalDocument (e.g. "Code du travail")
        └── LegalDocumentVersion
              ├── LegalArticle → LegalChunk (text snippets with embeddings)
              └── LegalChunk
```
Also has a legacy flat `Law` table with `LawTranslation`.

## Key Features
1. **Legal Corpus Ingestion Pipeline** — Scheduled daily (3 AM Casablanca), scrapes SGG PDFs/official bulletins, extracts text, structures into the data model
2. **Multi-Strategy Search Engine** — SQL-based relevance scoring across document titles, article numbers, full-text content, metadata, and cosine similarity on embeddings; has autocomplete suggestions
3. **Legal Domain Classifier** — Keyword-based classification into ~20 legal domains (labor, criminal, family, commercial, tax, etc.) with multilingual support (FR/EN/AR)
4. **Semantic Embeddings** — Local Ollama generates embeddings for chunks, enabling semantic similarity search
5. **Translation** — Free-tier APIs translate law titles/content between en/fr/ar, with chunking for long texts
6. **Authentication & Billing** — Email/password auth, optional Stripe subscription gated by env flag
7. **Trilingual UI** — Full English, French, Arabic support with locale switching
8. **Corpus Status Dashboard** — Public health metrics for the corpus at `/corpus/status`
9. **Workspace SPA** — Single-page search workspace with results cards, category browser, autocomplete, and a floating AI assistant popup

## API Routes (Key)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/corpus/status` | Corpus health |
| GET | `/api/laws/overview` | Corpus overview stats |
| GET | `/api/laws/search?q=&translation_mode=` | Main legal search |
| GET | `/api/laws/suggestions?q=` | Autocomplete |
| GET | `/api/laws/{law}/translate?target=` | Translate law article |
| POST | `/api/billing/stripe-webhook` | Stripe webhook |
| GET | `/dashboard`, `/search` | Workspace (auth+paid) |

## Artisan Commands
- `corpus:update-official-sources` — Main ingestion pipeline (scheduled daily)
- `corpus:embed-chunks` — Generate embeddings
- `BuildCorpusFromLegacyLaws`, `ImportLegacyLaws`, `ImportPdfLawSource` — Legacy imports
- `ReclassifyLegalCorpus` — Reclassify documents/articles

## Architecture Notes
- **Dual data model:** Both legacy flat `Law` table and new structured corpus coexist for gradual migration
- **Local AI-first:** All inference runs via local Ollama (no cloud API costs, GDPR-friendly)
- **Scoring-based search:** Custom SQL scoring with weighted signals instead of Elasticsearch
- **Multi-lingual by design:** Classification, search, translation, and UI all trilingual
- **Database-backed queue:** For batch PDF downloads and processing
- **Configurable billing:** `BILLING_REQUIRE_PAYMENT` env flag switches between free and paid modes
- **No Docker/CI:** No Dockerfile or CI configuration present; manual Laravel deployment

## Database Schema (All Tables)

### `legal_sources` — Root source (e.g. "Bulletin officiel n° 5210")
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| name | varchar(255) NOT NULL | Human-readable name |
| source_type | varchar(40) NOT NULL | e.g. "sgg_bulletin" |
| source_url | varchar(1024) NULL | |
| official_domain | varchar(255) NULL | |
| language | varchar(10) NULL | "fr", "ar", "en" |
| checksum | varchar(64) NULL | SHA-256 indexed |
| status | varchar(30) NOT NULL | default 'active', indexed |
| metadata | json NULL | |
| created_at | timestamp NULL | |
| updated_at | timestamp NULL | |

### `legal_documents` — A legal document (e.g. "Code du travail")
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| legal_source_id | bigint unsigned NULL | FK → legal_sources.id ON DELETE SET NULL |
| current_version_id | bigint unsigned NULL | FK → legal_document_versions.id (manual) |
| document_title | varchar(255) NOT NULL | |
| document_type | varchar(40) NOT NULL | e.g. "code", "loi", "decret" |
| law_reference | varchar(150) NULL | e.g. "Loi n° 65-99" |
| bo_number | varchar(80) NULL | e.g. "BO-5210" |
| publication_date | date NULL | |
| effective_date | date NULL | |
| language | varchar(10) NOT NULL | default 'fr' |
| domain | varchar(100) NULL | e.g. "droit du travail" |
| subdomain | varchar(120) NULL | |
| tags | json NULL | |
| source_url | varchar(1024) NULL | |
| checksum | varchar(64) NULL | SHA-256 indexed |
| status | varchar(30) NOT NULL | default 'active' |
| metadata | json NULL | |
| created_at | timestamp NULL | |
| updated_at | timestamp NULL | |

### `legal_document_versions` — Versioned snapshots of a document
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| legal_document_id | bigint unsigned NOT NULL | FK → legal_documents.id ON DELETE CASCADE |
| version_number | int unsigned NOT NULL | Unique per document |
| source_url | varchar(1024) NULL | |
| source_file_path | varchar(1024) NULL | Local path to PDF |
| checksum | varchar(64) NOT NULL | Unique per document |
| status | varchar(30) NOT NULL | default 'active' |
| publication_date | date NULL | |
| effective_date | date NULL | |
| imported_at | timestamp NULL | |
| raw_text | longtext NULL | Full extracted text |
| metadata | json NULL | |
| created_at | timestamp NULL | |
| updated_at | timestamp NULL | |

### `legal_articles` — Individual articles within a version
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| legal_document_id | bigint unsigned NOT NULL | FK → legal_documents.id ON DELETE CASCADE |
| legal_document_version_id | bigint unsigned NOT NULL | FK → legal_document_versions.id ON DELETE CASCADE |
| legacy_law_id | bigint unsigned NULL | FK → laws.id ON DELETE SET NULL |
| article_number | varchar(100) NOT NULL | e.g. "1", "1-2" |
| article_title | varchar(255) NULL | |
| content | longtext NOT NULL | Article body text |
| language | varchar(10) NOT NULL | default 'fr' |
| domain | varchar(100) NULL | |
| subdomain | varchar(120) NULL | |
| tags | json NULL | |
| checksum | varchar(64) NOT NULL | SHA-256 indexed |
| sort_order | int unsigned NOT NULL | default 0 |
| status | varchar(30) NOT NULL | default 'active' |
| metadata | json NULL | |
| created_at | timestamp NULL | |
| updated_at | timestamp NULL | |

### `legal_chunks` — Semantic text snippets (with embeddings)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| legal_article_id | bigint unsigned NOT NULL | FK → legal_articles.id ON DELETE CASCADE |
| legal_document_version_id | bigint unsigned NOT NULL | FK → legal_document_versions.id ON DELETE CASCADE |
| chunk_index | int unsigned NOT NULL | 0-based within article |
| content | longtext NOT NULL | Text snippet |
| token_count | int unsigned NOT NULL | default 0 |
| domain | varchar(100) NULL | |
| subdomain | varchar(120) NULL | |
| tags | json NULL | |
| checksum | varchar(64) NOT NULL | SHA-256 indexed |
| metadata | json NULL | |
| embedding | json NULL | Float vector array |
| embedding_model | varchar(120) NULL | e.g. "nomic-embed-text" |
| embedding_checksum | varchar(64) NULL | |
| embedded_at | timestamp NULL | |
| created_at | timestamp NULL | |
| updated_at | timestamp NULL | |

### `laws` — Legacy flat table (pre-migration)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| title | varchar(255) | |
| article_number | varchar(100) | |
| content | longtext | |
| tags | json | |
| document_title | varchar(255) | |
| law_reference | varchar(150) | |
| category | varchar(100) | |
| source_name | varchar(255) | |
| source_url | varchar(1024) | |
| language | varchar(10) | |
| imported_at | timestamp | |

### `law_translations` — Legacy translations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| law_id | bigint unsigned | FK → laws.id |
| title | varchar(255) | |
| content | longtext | |
| language | varchar(10) | |
| created_at | timestamp | |
| updated_at | timestamp | |


## Config Files to Know
- `config/legal_ai.php` — Ollama model config
- `config/legal_sources.php` — SGG source URLs and settings
- `config/billing.php` — Stripe/trial config
- `.env.production.example` — Production environment template
