import scrapy
from datetime import datetime
from w3lib.url import safe_url_string
import re

class LawsSpider(scrapy.Spider):
    name = "laws_spider"
    allowed_domains = ["adala.justice.gov.ma"]
    
    # ⚡ Start URLs for both French and Arabic resources
    start_urls = [
        "https://adala.justice.gov.ma/fr/resources",
        "https://adala.justice.gov.ma/ar/resources"
    ]
    
    visited_urls = set()

    def parse(self, response):
        if response.url in self.visited_urls:
            return
        self.visited_urls.add(response.url)

        # 1. Targeted PDF Extraction
        for row in response.css('tr'):
            link_element = row.css('a.lien_plansite')
            if link_element:
                raw_url = link_element.css('::attr(href)').get()
                title = link_element.css('span.titre_plansite::text').get()
                
                if raw_url and title:
                    clean_pdf_url = raw_url.split('#')[0].split('?')[0]
                    full_pdf_url = response.urljoin(clean_pdf_url)
                    
                    yield scrapy.Request(
                        url=safe_url_string(full_pdf_url),
                        callback=self.save_pdf,
                        meta={
                            'title': title.strip(),
                            'category': self.classify_law(title),
                            'timestamp': datetime.now().isoformat()
                        },
                        headers={'Referer': response.url}
                    )

        # 2. Recursive Folder Traversal (Supports both /fr/resources and /ar/resources paths)
        category_links = response.css('a[href*="/resources/"]::attr(href)').getall()
        for link in category_links:
            full_url = response.urljoin(link)
            # Ensure we allow crawling through both language structures
            if ("/resources/" in full_url) and (full_url not in self.visited_urls):
                yield response.follow(full_url, callback=self.parse)

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
        
        # ⚡ Bilingual Mapping (French + Arabic equivalent keywords)
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