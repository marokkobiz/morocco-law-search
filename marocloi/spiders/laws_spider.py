import scrapy
from datetime import datetime
from w3lib.url import safe_url_string
from urllib.parse import urlparse, unquote
import os
import re

class LawsSpider(scrapy.Spider):
    name = "laws_spider"
    allowed_domains = ["adala.justice.gov.ma"]
    
    start_urls = [
        "https://adala.justice.gov.ma/fr/resouces",
        "https://adala.justice.gov.ma/ar/resources"
    ]
    
    visited_pages = set()
    discovered_pdfs = set()

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'ROBOTSTXT_OBEY': False
    }

    def parse(self, response):
        if not hasattr(response, "text"):
            return

        current_url = response.url.split('#')[0].split('?')[0]
        if current_url in self.visited_pages:
            return
        self.visited_pages.add(current_url)

        all_links = response.css('a')
        for link in all_links:
            href = link.css('::attr(href)').get()
            if not href:
                continue

            full_url = response.urljoin(href).split('#')[0]
            clean_url = safe_url_string(full_url)
            
            link_text = " ".join(link.css('::text, *::text').getall()).strip()

            if self.is_pdf_link(clean_url):
                if clean_url not in self.discovered_pdfs:
                    self.discovered_pdfs.add(clean_url)
                    
                    if not link_text:
                        parsed_url = urlparse(clean_url)
                        link_text = unquote(os.path.basename(parsed_url.path))
                    
                    # ⚡ Check if the PDF is a Royal Message / Speech
                    if self.is_blacklisted(link_text, clean_url):
                        self.logger.info(f"⏭️ Skipping non-law document: {link_text}")
                        continue
                    
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
            
            elif self.is_crawlable_page(clean_url):
                yield response.follow(clean_url, callback=self.parse)

    def is_pdf_link(self, url: str) -> bool:
        parsed = urlparse(url)
        path = parsed.path.lower()
        return path.endswith('.pdf') or '.pdf' in path

    def is_crawlable_page(self, url: str) -> bool:
        parsed = urlparse(url)
        if parsed.netloc not in self.allowed_domains:
            return False
            
        path = parsed.path.lower()
        excluded_extensions = ('.zip', '.tar', '.gz', '.png', '.jpg', '.jpeg', '.gif', '.mp4', '.mp3', '.pdf')
        if path.endswith(excluded_extensions):
            return False
            
        # ⚡ Avoid crawling entire web directories dedicated solely to Royal Speeches if possible
        if "discours" in path or "messages" in path or "خطب" in path or "رسائل" in path:
            return False

        return True

    def is_blacklisted(self, title: str, url: str) -> bool:
        """⚡ Filters out non-legislative items like Royal Speeches/Letters."""
        title_lower = title.lower()
        url_lower = url.lower()
        
        # Keywords for Royal Speeches / Letters
        blacklist_keywords = [
            # French
            "discours royal", "discours royaux", "message royal", "messages royaux", 
            "lettre royale", "lettres royales", "allocution",
            # Arabic
            "خطاب ملكي", "الخطب الملكية", "رسالة ملكية", "الرسائل الملكية", "رسائل ملكية",
            "خطب جلالة", "رسالة جلالة","الرسالة الملكية","خطاب صاحب الجلالة الملك ","الكلمة الملكية ","رسالة صاحب جلالة " ,"الرسالة السامية", "الخطاب السامي"
        ]
        
        # Check title
        if any(keyword in title_lower for keyword in blacklist_keywords):
            return True
            
        # Check URL paths (e.g., /fr/discours/ or /ar/messages/)
        if any(keyword in url_lower for keyword in ["discours", "message", "خطاب", "رسالة"]):
            # Double check to ensure we don't block laws that just mention a "date of message"
            if not any(law_word in url_lower for law_word in ["loi", "dahir", "decret", "arrêté", "قانون", "ظهير", "مرسوم"]):
                return True
                
        return False

    def save_pdf(self, response):
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