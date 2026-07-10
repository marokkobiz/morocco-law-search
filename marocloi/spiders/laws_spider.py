import scrapy
import os
import re

class LawsSpider(scrapy.Spider):
    name = "laws_spider"
    start_urls = []
    
    # Re-adding these so your 'if' statements work
    pdf_p = re.compile(r"\.(pdf|docx?|xlsx?)(?:\?|$)", re.IGNORECASE)
    rel_p = re.compile(r"loi|d[eé]cret|arr[eé]t[e]?|r[eè]glement|circulaire|t[eé]l[eé]charger|pdf|document|download|juridique", re.IGNORECASE)

    def __init__(self, *args, **kwargs):
        super(LawsSpider, self).__init__(*args, **kwargs)
        seeds_file = os.path.join(os.getcwd(), 'seeds.txt')
        if os.path.exists(seeds_file):
            with open(seeds_file, 'r', encoding='utf-8') as f:
                self.start_urls = [line.strip() for line in f if line.strip()]
            self.logger.info(f"Loaded {len(self.start_urls)} URLs from {seeds_file}")
        else:
            self.logger.error(f"FILE NOT FOUND: {seeds_file}")

    def parse(self, response):
        self.logger.info(f"SUCCESS: Crawling {response.url}")
        
        # Ensure we only parse HTML
        if 'text/html' not in response.headers.get('Content-Type', b'').decode('utf-8').lower():
            return

        # Corrected indentation here
        for href in response.css('a::attr(href)').getall():
            full_url = response.urljoin(href)
            
            if self.pdf_p.search(full_url):
                title = os.path.basename(full_url)
                yield {
                    'title': title,
                    'file_urls': [full_url],
                    'category': self.classify_law(title)
                }
            elif self.rel_p.search(href):
                yield response.follow(full_url, callback=self.parse)

    def classify_law(self, title):
        m = {"Constitutional":["constitution","dahir","royaume"],"Civil":["civil","famille","contrat"],
             "Criminal":["pénal","crime","délit"],"Business":["commercial","société","fiscal","finance"]}
        scores = {k: sum(kw in title.lower() for kw in v) for k, v in m.items()}
        return max(scores, key=scores.get) if any(scores.values()) else "Other"