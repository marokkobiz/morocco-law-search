import scrapy
from datetime import datetime
from w3lib.url import safe_url_string
from urllib.parse import urlparse, unquote
import os
import re

class LawsSpider(scrapy.Spider):
    name = "laws_spider"
    allowed_domains = ["adala.justice.gov.ma"]
    
    # ⚡ Start at the root language gates to discover everything
    start_urls = [
        "https://adala.justice.gov.ma/fr/resources",
        "https://adala.justice.gov.ma/ar/resources"
    ]
    
    # Sets to track discovered items and avoid duplicates
    visited_pages = set()
    discovered_pdfs = set()

    # Configure Scrapy speed settings to be gentle but thorough
    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,  # 0.5s delay to prevent getting blocked
        'ROBOTSTXT_OBEY': False
    }

    def parse(self, response):
        # Ignore non-HTML pages (like direct image links or documents)
        if not hasattr(response, "text"):
            return

        current_url = response.url.split('#')[0].split('?')[0]
        if current_url in self.visited_pages:
            return
        self.visited_pages.add(current_url)

        # 1. Aggressively scan ALL <a> links on the page
        all_links = response.css('a')
        for link in all_links:
            href = link.css('::attr(href)').get()
            if not href:
                continue

            # Clean and build the full absolute URL
            full_url = response.urljoin(href).split('#')[0]
            clean_url = safe_url_string(full_url)
            
            # Extract whatever text is inside the link (falls back to clean filename if empty)
            link_text = " ".join(link.css('::text, *::text').getall()).strip()

            # 🛑 Check if it's a PDF link
            if self.is_pdf_link(clean_url):
                if clean_url not in self.discovered_pdfs:
                    self.discovered_pdfs.add(clean_url)
                    
                    # Generate a fallback title from the file name if the link text is empty
                    if not link_text:
                        parsed_url = urlparse(clean_url)
                        link_text = unquote(os.path.basename(parsed_url.path))
                    
                    yield scrapy.Request(
                        url=clean_url,
                        callback=self.save_pdf,
                        meta={
                            'title': link_text,
                            'category': self.classify_law(link_text),
                            'timestamp': datetime.now().isoformat()
                        },
                        headers={'Referer': response.url}
                    )
            
            # 🔄 Check if it is a recursive page we should crawl
            elif self.is_crawlable_page(clean_url):
                yield response.follow(clean_url, callback=self.parse)

    def is_pdf_link(self, url: str) -> bool:
        """Helper to check if a URL points to a PDF, ignoring query params."""
        parsed = urlparse(url)
        path = parsed.path.lower()
        return path.endswith('.pdf') or '.pdf' in path

    def is_crawlable_page(self, url: str) -> bool:
        """Ensures we stay on adala.justice.gov.ma and don't download media files as HTML."""
        parsed = urlparse(url)
        
        # Must be within the allowed domain
        if parsed.netloc not in self.allowed_domains:
            return False
            
        path = parsed.path.lower()
        
        # Exclude common media/binary assets
        excluded_extensions = ('.zip', '.tar', '.gz', '.png', '.jpg', '.jpeg', '.gif', '.mp4', '.mp3', '.pdf')
        if path.endswith(excluded_extensions):
            return False
            
        return True

    def save_pdf(self, response):
        """Callback to process the actual file download."""
        yield {
            'title': response.meta['title'],
            'file_urls': [response.url],
            'category': response.meta['category'],
            'timestamp': response.meta['timestamp'],
        }

    def classify_law(self, title: str) -> str:
        t = title.lower()
        scores = {"Constitutional": 0, "Civil": 0, "Criminal": 0, "Business": 0}
        
        mapping = {
            "Constitutional": [
                "constitution", "dahir", "législatif", "droit public", "royaume", 
                "دستور", "ظهير", "تشريعي", "قانون عام", "مملكة"
            ],
            "Civil": [
                "civil", "famille", "statut personnel", "héritage", "mariage", "divorce", "contrat",
                "مدني", "أسرة", "الأحوال الشخصية", "إرث", "زواج", "طلاق", "عقد"
            ],
            "Criminal": [
                "pénal", "procédure pénale", "pénitentiaire", "crime", "délit", "infraction",
                "جنائي", "المسطرة الجنائية", "سجون", "جريمة", "جنحة", "مخالفة"
            ],
            "Business": [
                "commercial", "société", "investissement", "fiscal", "assurance", "marché", "bancaire", "taxe",
                "تجاري", "شركة", "استثمار", "ضريبي", "تأمين", "صفقة", "بنكي", "ضريبة"
            ]
        }
        
        for category, keywords in mapping.items():
            if any(kw in t for kw in keywords):
                scores[category] += 1
        
        best_match = max(scores, key=scores.get)
        return best_match if scores[best_match] > 0 else "Other"