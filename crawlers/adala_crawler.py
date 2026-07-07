import re
import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlencode
import time

from config import ADALA_BASE_URL, ADALA_SEARCH_URL, ADALA_RESOURCES_URL, ADALA_NEW_RELEASES_URL, USER_AGENT, REQUEST_TIMEOUT


class AdalaCrawler:
    def __init__(self, db_manager):
        self.db = db_manager
        self.session = requests.Session()
        self.session.headers.update({"User-Agent": USER_AGENT})

    def search(self, query, page=1, resources=None, themes=None, law_types=None):
        params = {"term": query, "page": page}
        if resources:
            params["resources"] = ",".join(resources)
        if themes:
            params["themes"] = ",".join(themes)
        if law_types:
            params["type"] = ",".join(law_types)
        url = f"{ADALA_SEARCH_URL}?{urlencode(params)}"
        resp = self.session.get(url, timeout=REQUEST_TIMEOUT)
        resp.encoding = "utf-8"
        return resp.text

    def parse_search_results(self, html):
        soup = BeautifulSoup(html, "lxml")
        results = []

        result_section = soup.find("section", class_="bg-adala-blue-accent-50")
        if not result_section:
            result_items = soup.find_all("div", class_="group")
            if not result_items:
                return results

        cards = soup.find_all("div", class_="group")
        for card in cards:
            title_elem = card.find("p", class_="font-sans")
            if not title_elem:
                continue
            title = title_elem.get_text(strip=True)
            if not title or title == "undefined":
                title_div = card.find("p")
                if title_div:
                    title = title_div.get_text(strip=True)

            link = card.find("a", href=True)
            law_url = urljoin(ADALA_BASE_URL, link["href"]) if link else ""

            spans = card.find_all("span")
            law_type = ""
            for span in spans:
                text = span.get_text(strip=True)
                if text in ["Dahir", "Loi", "Décret", "Arrêté", "Décision",
                            "Dahir", "Loi", "Décret", "Arrêté", "Décision",
                            "Avis", "Bilatérale", "Multilatérale",
                            "Convention", " circulaire", "Note"]:
                    law_type = text
                    break

            lists = card.find_all("li")
            theme = ""
            number = ""
            date_text = ""
            for li in lists:
                li_text = li.get_text(strip=True)
                if ":" in li_text and "theme" in li_text.lower():
                    theme = li_text.split(":", 1)[1].strip()
                elif ":" in li_text and "number" in li_text.lower():
                    number = li_text.split(":", 1)[1].strip()
                elif li.find("p"):
                    p = li.find("p")
                    strong = p.find("strong") if p else None
                    if strong:
                        p_text = p.get_text(strip=True)
                        if ":" in p_text:
                            date_text = p_text.split(":", 1)[1].strip() if ":" in p_text else ""

            type_match = re.search(
                r"(Dahir|Loi|Décret|Arrêté|Décision)", title, re.IGNORECASE
            )
            if not law_type and type_match:
                law_type = type_match.group(1)

            row_html = str(card)
            law = {
                "title": title,
                "type": law_type,
                "number": number,
                "date_gregorian": date_text,
                "date_islamic": "",
                "source": "Adala",
                "source_url": ADALA_SEARCH_URL,
                "law_url": law_url,
                "summary": title,
                "content": title,
                "theme": theme,
                "raw_html": row_html,
            }
            results.append(law)

        return results

    def fetch_law_detail(self, law_url):
        if not law_url:
            return ""
        try:
            resp = self.session.get(law_url, timeout=REQUEST_TIMEOUT)
            resp.encoding = "utf-8"
            return resp.text
        except Exception:
            return ""

    def crawl_keyword(self, keyword, max_pages=3):
        all_laws = []
        for page in range(1, max_pages + 1):
            html = self.search(keyword, page=page)
            results = self.parse_search_results(html)
            if not results:
                break
            all_laws.extend(results)
            for law in results:
                if not self.db.law_exists(law["title"], law["source"]):
                    self.db.insert_law(law)
        return all_laws

    def crawl_resources(self):
        resp = self.session.get(ADALA_RESOURCES_URL, timeout=REQUEST_TIMEOUT)
        resp.encoding = "utf-8"
        soup = BeautifulSoup(resp.text, "lxml")
        links = soup.find_all("a", href=True)
        laws = []
        for link in links:
            href = link["href"]
            text = link.get_text(strip=True)
            if "/resources/" in href and text:
                resource_url = urljoin(ADALA_BASE_URL, href)
                try:
                    r = self.session.get(resource_url, timeout=REQUEST_TIMEOUT)
                    r.encoding = "utf-8"
                    sub_soup = BeautifulSoup(r.text, "lxml")
                    for sub_link in sub_soup.find_all("a", href=True):
                        sub_href = sub_link["href"]
                        sub_text = sub_link.get_text(strip=True)
                        if len(sub_text) > 30 and any(
                            kw in sub_text.lower()
                            for kw in ["dahir", "loi", "décret", "arrêté", "décision"]
                        ):
                            law = {
                                "title": sub_text,
                                "type": "",
                                "number": "",
                                "date_gregorian": "",
                                "date_islamic": "",
                                "source": "Adala",
                                "source_url": resource_url,
                                "law_url": urljoin(ADALA_BASE_URL, sub_href),
                                "summary": sub_text,
                                "content": sub_text,
                                "theme": "",
                                "raw_html": str(sub_link),
                            }
                            type_match = re.search(
                                r"(Dahir|Loi|Décret|Arrêté|Décision)", sub_text, re.IGNORECASE
                            )
                            if type_match:
                                law["type"] = type_match.group(1)
                            laws.append(law)
                            if not self.db.law_exists(law["title"], law["source"]):
                                self.db.insert_law(law)
                except Exception:
                    continue
        return laws

    def crawl_new_releases(self):
        resp = self.session.get(ADALA_NEW_RELEASES_URL, timeout=REQUEST_TIMEOUT)
        resp.encoding = "utf-8"
        soup = BeautifulSoup(resp.text, "lxml")
        laws = []
        items = soup.find_all("div", class_="group")
        for item in items:
            title_elem = item.find("p", class_="font-sans")
            title = title_elem.get_text(strip=True) if title_elem else ""
            if not title or title == "":
                continue
            link = item.find("a", href=True)
            law_url = urljoin(ADALA_BASE_URL, link["href"]) if link else ""
            law = {
                "title": title,
                "type": "",
                "number": "",
                "date_gregorian": "",
                "date_islamic": "",
                "source": "Adala",
                "source_url": ADALA_NEW_RELEASES_URL,
                "law_url": law_url,
                "summary": title,
                "content": title,
                "theme": "",
                "raw_html": str(item),
            }
            type_match = re.search(
                r"(Dahir|Loi|Décret|Arrêté|Décision)", title, re.IGNORECASE
            )
            if type_match:
                law["type"] = type_match.group(1)
            laws.append(law)
            if not self.db.law_exists(law["title"], law["source"]):
                self.db.insert_law(law)
        return laws

    def run_full_crawl(self, keywords=None):
        if keywords is None:
            keywords = [
                "droit", "loi", "code", "travail", "famille",
                "impôt", "commercial", "pénal", "civil", "foncier",
                "eau", "environnement", "investissement", "social",
                "éducation", "santé", "agriculture", "énergie",
                "assurance", "consommation", "élection",
                "fiscal", "marché", "pénitentiaire",
            ]

        all_laws = []
        for keyword in keywords:
            laws = self.crawl_keyword(keyword)
            all_laws.extend(laws)

        all_laws.extend(self.crawl_resources())
        all_laws.extend(self.crawl_new_releases())

        self.db.update_crawler_state(
            "Adala", "completed", len(all_laws)
        )
        return all_laws
