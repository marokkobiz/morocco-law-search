import os

# --- Core Scrapy Settings ---
BOT_NAME = "marocloi"
SPIDER_MODULES = ["marocloi.spiders"]
NEWSPIDER_MODULE = "marocloi.spiders"
USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
ROBOTSTXT_OBEY = False
FEED_EXPORT_ENCODING = "utf-8"

# --- Pipeline Settings ---
ITEM_PIPELINES = {
    'marocloi.pipelines.LawFilePipeline': 1,
}
FILES_STORE = os.path.join(os.getcwd(), 'downloaded_laws')
MEDIA_ALLOW_REDIRECTS = True
FILES_EXPIRES = 90
# This is the critical setting for the pipeline downloaders
FILES_DOWNLOAD_TIMEOUT = 180 

# --- Downloader & Timeout Settings ---
# This is the global timeout for all requests, including file downloads
DOWNLOAD_TIMEOUT = 180 
DOWNLOAD_MAXSIZE = 0
COOKIES_ENABLED = False

# --- Concurrency & Politeness ---
# Keeping these conservative to avoid the Finance/Justice servers blocking you
CONCURRENT_REQUESTS = 8
CONCURRENT_REQUESTS_PER_DOMAIN = 2
DOWNLOAD_DELAY = 5
AUTOTHROTTLE_ENABLED = True
AUTOTHROTTLE_TARGET_CONCURRENCY = 2.0

# --- Retry Logic ---
RETRY_ENABLED = True
RETRY_TIMES = 3
RETRY_HTTP_CODES = [500, 502, 503, 504, 522, 524, 408]

# --- Headers ---
DEFAULT_REQUEST_HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language': 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
}
DOWNLOAD_HANDLERS = {
    "http": "scrapy_playwright.handler.ScrapyPlaywrightDownloadHandler",
    "https": "scrapy_playwright.handler.ScrapyPlaywrightDownloadHandler",
}