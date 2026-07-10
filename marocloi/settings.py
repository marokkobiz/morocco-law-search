# Scrapy settings for marocloi project
#
# For simplicity, this file contains only settings considered important or
# commonly used. You can find more settings consulting the documentation:
#
#     https://docs.scrapy.org/en/latest/topics/settings.html
#     https://docs.scrapy.org/en/latest/topics/downloader-middleware.html
#     https://docs.scrapy.org/en/latest/topics/spider-middleware.html

# Enable file redirects
MEDIA_ALLOW_REDIRECTS = True

# Ensure the pipeline waits longer for the file server
FILES_DOWNLOAD_TIMEOUT = 60 

# Add a standard browser header so the finance server doesn't reject you
DEFAULT_REQUEST_HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language': 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
}

BOT_NAME = "marocloi"

SPIDER_MODULES = ["marocloi.spiders"]
NEWSPIDER_MODULE = "marocloi.spiders"

ADDONS = {}


# Crawl responsibly by identifying yourself (and your website) on the user-agent
USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'

# Obey robots.txt rules
ROBOTSTXT_OBEY = False

# Concurrency and throttling settings
#CONCURRENT_REQUESTS = 16
CONCURRENT_REQUESTS_PER_DOMAIN = 1
DOWNLOAD_DELAY = 1
FEED_EXPORT_ENCODING = "utf-8"

# 1. Enable the built-in Files Pipeline
ITEM_PIPELINES = {
    'marocloi.pipelines.LawFilePipeline': 1,
}

# Ensure the root directory is set
import os
FILES_STORE = os.path.join(os.getcwd(), 'downloaded_laws')


# settings.py
ROBOTSTXT_OBEY = False  # Ignore robots.txt rules
DOWNLOAD_DELAY = 5           # Wait 5 seconds between requests
AUTOTHROTTLE_ENABLED = True  # Automatically slows down if the site is busy
CLOSESPIDER_ITEMCOUNT = 5
# 1. Increase concurrency to handle multiple sites at once
CONCURRENT_REQUESTS = 64  # Increased from default 16
CONCURRENT_REQUESTS_PER_DOMAIN = 4 # Keep this low to remain polite to each site

# 2. Disable/Adjust features that slow down multi-site crawls
COOKIES_ENABLED = False
RETRY_ENABLED = False  # Set to True only if you have many network errors
DOWNLOAD_TIMEOUT = 30  # Fail faster on slow sites to move to the next
AUTOTHROTTLE_ENABLED = True # Disable if you want maximum speed, but be careful
AUTOTHROTTLE_TARGET_CONCURRENCY = 2.0
# 3. Ensure the OffsiteMiddleware doesn't block your domains
# If you aren't using an allowed_domains list, Scrapy defaults to allowing everything.
# Just ensure your logic doesn't manually filter them out.
REDIRECT_ENABLED = True
REDIRECT_MAX_TIMES = 5
FILES_EXPIRES = 90

