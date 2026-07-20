# 📚 Morocco Law Search & Data Processing Pipeline

Welcome to the **Morocco Law Search** project! This branch of repository contains a Prototype made by Adam Akhaddar of an automated end-to-end pipeline designed to scrape, extract, parse, chunk, categorize Moroccan legal documents, and serve them through a web interface.

---

## 📁 Repository Overview & Directory Structure

Here is a breakdown of what each folder and file in this repository does:

```text
morocco-law-search/
│
├── marocloi/              # Web scraping project directory (Scrapy setup)
├── downloaded_laws/       # Raw PDF or HTML files downloaded from legal sources
├── extracted_laws/        # Text extracted from raw legal files
├── json_laws/             # Structured JSON data ready for indexing/searching
│
├── Website.py             # Web application interface (Streamlit / Web Backend)
├── run_everything.py      # Master orchestrator script to run the entire pipeline
├── Parser.py              # Parses raw text into structured formats
├── Chunker.py             # Splits long legal texts into smaller, searchable chunks
├── Categorizer.py         # Assigns legal categories/tags to parsed documents
│
├── docker-compose.yml     # Container configuration for running the project in Docker
├── scrapy.cfg             # Scrapy configuration file for web crawlers
├── Session Initializer.txt# Setup / session tracking configuration
└── .env.example           # Template for environment configuration variables

```

---

## ⚙️ How the Pipeline Works

NB: Take a look at the requirements.txt file to make sure ALL pre-requisites are INSTALLED

The project follows a **5-step data pipeline** from initial scraping to web display:

```
[1. Scrape & Download] ➔ [2. Text Extraction] ➔ [3. Parsing & Categorization] ➔ [4. Chunking] ➔ [5. Search Interface]

```

1. **Scraping (`marocloi/` & `scrapy.cfg`)**
* Automatically crawls official legal portals to download Moroccan laws into the `downloaded_laws/` folder.


2. **Extraction (`extracted_laws/`)**
* Converts downloaded documents into readable raw text format.


3. **Parsing & Categorization (`Parser.py` & `Categorizer.py`)**
* Extracts key metadata (articles, dates, law numbers) and organizes them into structured JSON files inside `json_laws/`.


4. **Chunking (`Chunker.py`)**
* Breaks long legal texts into smaller, semantically coherent segments suitable for accurate searching and indexing.


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

## 🛠️ Environment Setup

1. Copy `.env.example` to create your own configuration file:
```bash
cp .env.example .env

```


2. Open `.env` and fill in any required environment variables (API keys, database URLs, port settings, etc.).
3. Also take a look the requirements.txt
