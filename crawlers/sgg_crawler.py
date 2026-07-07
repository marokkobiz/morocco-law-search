import re
import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin
import time

from config import SGG_BASE_URL, SGG_SEARCH_URL, USER_AGENT, REQUEST_TIMEOUT


class SGGCrawler:
    def __init__(self, db_manager):
        self.db = db_manager
        self.session = requests.Session()
        self.session.headers.update({"User-Agent": USER_AGENT})

    def _get_viewstate(self, html):
        soup = BeautifulSoup(html, "lxml")
        viewstate = soup.find("input", {"id": "__VIEWSTATE"})
        viewstate_gen = soup.find("input", {"id": "__VIEWSTATEGENERATOR"})
        event_validation = soup.find("input", {"id": "__EVENTVALIDATION"})
        return {
            "__VIEWSTATE": viewstate.get("value", "") if viewstate else "",
            "__VIEWSTATEGENERATOR": (
                viewstate_gen.get("value", "") if viewstate_gen else ""
            ),
            "__EVENTVALIDATION": (
                event_validation.get("value", "") if event_validation else ""
            ),
        }

    def _get_search_page(self):
        resp = self.session.get(SGG_SEARCH_URL, timeout=REQUEST_TIMEOUT)
        resp.encoding = "utf-8"
        return resp.text

    def search(self, query, nature="", date_deb="", date_fin="", page_size=20):
        html = self._get_search_page()
        state = self._get_viewstate(html)

        soup = BeautifulSoup(html, "lxml")
        titre_txt = soup.find("input", {"id": "dnn_ctr2721_View_titre_txt"})
        titre_name = (
            titre_txt.get("name", "dnn$ctr2721$View$titre_txt")
            if titre_txt
            else "dnn$ctr2721$View$titre_txt"
        )

        data = {
            "__VIEWSTATE": state["__VIEWSTATE"],
            "__VIEWSTATEGENERATOR": state["__VIEWSTATEGENERATOR"],
            "__EVENTVALIDATION": state["__EVENTVALIDATION"],
            titre_name: query,
            "dnn$ctr2721$View$BTN_Rech": "Rechercher",
            "dnn$ctr2721$View$nbr_page": str(page_size),
            "dnn$ctr2721$View$Box_ar$0": "on",
            "dnn$ctr2721$View$Type_rech$1": "on",
        }

        text_natures = {
            "dahir": "dnn$ctr2721$View$Txt_nat$0",
            "loi": "dnn$ctr2721$View$Txt_nat$1",
            "traite": "dnn$ctr2721$View$Txt_nat$2",
            "decret": "dnn$ctr2721$View$Txt_nat$3",
            "arrete": "dnn$ctr2721$View$Txt_nat$4",
            "decision": "dnn$ctr2721$View$Txt_nat$5",
        }
        if nature and nature in text_natures:
            data[text_natures[nature]] = "on"

        if date_deb:
            data["dnn$ctr2721$View$date_bo_deb"] = date_deb
        if date_fin:
            data["dnn$ctr2721$View$date_bo_fin"] = date_fin

        resp = self.session.post(
            SGG_SEARCH_URL, data=data, timeout=REQUEST_TIMEOUT
        )
        resp.encoding = "utf-8"
        return resp.text

    def parse_results(self, html):
        soup = BeautifulSoup(html, "lxml")
        results = []

        result_items = soup.find_all("div", class_="sgg-search-item")
        for item in result_items:
            title_tag = item.select_one(".content-search-result > p")
            if not title_tag:
                continue

            title = title_tag.get_text(strip=True)
            if not title:
                continue

            date_tag = item.select_one(".date-result .color_date")
            date_text = date_tag.get_text(strip=True) if date_tag else ""
            date_text = date_text.replace("Publié le ", "").strip()

            bo_tag = item.select_one(".num-txt a.grass")
            law_url = ""
            bo_number = ""
            if bo_tag and bo_tag.get("href"):
                law_url = urljoin(SGG_BASE_URL, bo_tag["href"])
                bo_number = bo_tag.get_text(strip=True)

            type_match = re.search(
                r"(Dahir|Loi|Décret|Arrêté|Décision|Traite)", title, re.IGNORECASE
            )
            law_type = type_match.group(1) if type_match else ""

            number_match = re.search(r"n[°º]\s*([\d\-\.]+)", title)
            number = number_match.group(1) if number_match else ""

            law = {
                "title": title,
                "type": law_type,
                "number": number,
                "date_gregorian": date_text,
                "date_islamic": "",
                "source": "SGG",
                "source_url": SGG_SEARCH_URL,
                "law_url": law_url,
                "summary": title,
                "content": title,
                "theme": "",
                "raw_html": str(item),
            }
            results.append(law)

        return results

    def crawl_keyword(self, keyword, nature="", max_pages=1):
        laws_found = []
        for page in range(max_pages):
            html = self.search(keyword, nature=nature)
            results = self.parse_results(html)
            if not results:
                break
            laws_found.extend(results)
            for law in results:
                if not self.db.law_exists(law["title"], law["source"]):
                    self.db.insert_law(law)
        return laws_found

    def _parse_accordion_laws(self, html, section_label="Textes Consolidés"):
        soup = BeautifulSoup(html, "lxml")
        accordion = soup.find("div", {"id": "Agg3250_Accordion"})
        if not accordion:
            accordion = soup.find("div", id=re.compile(r"Accordion"))
        if not accordion:
            return self._parse_all_links(soup, section_label)

        laws = []
        for section in accordion.find_all("h3"):
            section_title = section.get_text(strip=True)
            content_div = section.find_next_sibling("div")
            if not content_div:
                continue
            inner = content_div.find("div", style=re.compile(r"margin"))
            if not inner:
                inner = content_div
            for p_tag in inner.find_all("p"):
                a_tag = p_tag.find("a", href=True)
                if not a_tag:
                    continue
                title = a_tag.get_text(strip=True)
                law_url = urljoin(SGG_BASE_URL, a_tag["href"])
                if not title:
                    continue
                type_match = re.search(
                    r"(Dahir|Loi|Décret|Arrêté|Décision)", title, re.IGNORECASE
                )
                law_type = type_match.group(1) if type_match else ""
                number_match = re.search(r"n[°º]\s*([\d\-\.]+)", title)
                number = number_match.group(1) if number_match else ""
                law = {
                    "title": title,
                    "type": law_type,
                    "number": number,
                    "date_gregorian": "",
                    "date_islamic": "",
                    "source": "SGG",
                    "source_url": section_label,
                    "law_url": law_url,
                    "summary": title,
                    "content": title,
                    "theme": section_title,
                    "raw_html": str(a_tag),
                }
                laws.append(law)
                if not self.db.law_exists(law["title"], law["source"]):
                    self.db.insert_law(law)
        return laws

    def _parse_all_links(self, soup, section_label="SGG"):
        laws = []
        for link in soup.find_all("a", href=True):
            href = link["href"]
            text = link.get_text(strip=True)
            if len(text) > 20 and any(
                kw in text.lower()
                for kw in ["dahir", "loi", "décret", "arrêté", "décision"]
            ):
                law = {
                    "title": text,
                    "type": "",
                    "number": "",
                    "date_gregorian": "",
                    "date_islamic": "",
                    "source": "SGG",
                    "source_url": section_label,
                    "law_url": urljoin(SGG_BASE_URL, href),
                    "summary": text,
                    "content": text,
                    "theme": "",
                    "raw_html": str(link),
                }
                law_type_match = re.search(
                    r"(Dahir|Loi|Décret|Arrêté|Décision)", text, re.IGNORECASE
                )
                if law_type_match:
                    law["type"] = law_type_match.group(1)
                laws.append(law)
                if not self.db.law_exists(law["title"], law["source"]):
                    self.db.insert_law(law)
        return laws

    def crawl_textes_consolides(self):
        url = f"{SGG_BASE_URL}/textesconsolides.aspx"
        resp = self.session.get(url, timeout=REQUEST_TIMEOUT)
        resp.encoding = "utf-8"
        return self._parse_accordion_laws(resp.text, "Textes Consolidés")

    def crawl_textes_importants(self):
        url = f"{SGG_BASE_URL}/textesimportants.aspx"
        resp = self.session.get(url, timeout=REQUEST_TIMEOUT)
        resp.encoding = "utf-8"
        soup = BeautifulSoup(resp.text, "lxml")
        laws = []
        for link in soup.find_all("a", href=True):
            text = link.get_text(strip=True)
            href = link["href"]
            if not text or len(text) < 10:
                continue
            if not any(kw in text.lower() for kw in
                       ["dahir", "loi", "décret", "arrêté", "décision", "loi-cadre", "loi organique"]):
                continue
            if "circulaire" in text.lower() and "circulaire" not in text.lower()[:15]:
                continue
            law_url = urljoin(SGG_BASE_URL, href)
            type_match = re.search(
                r"(Dahir|Loi|Décret|Arrêté|Décision|Loi-cadre|Loi organique)", text, re.IGNORECASE
            )
            law_type = type_match.group(1) if type_match else ""
            number_match = re.search(r"n[°º]\s*([\d\-\.]+)", text)
            number = number_match.group(1) if number_match else ""
            law = {
                "title": text,
                "type": law_type,
                "number": number,
                "date_gregorian": "",
                "date_islamic": "",
                "source": "SGG",
                "source_url": url,
                "law_url": law_url,
                "summary": text,
                "content": text,
                "theme": "",
                "raw_html": str(link),
            }
            laws.append(law)
            if not self.db.law_exists(law["title"], law["source"]):
                self.db.insert_law(law)
        return laws

    def crawl_legislation_section(self, section_url):
        return self._parse_all_links(self.session.get(section_url, timeout=REQUEST_TIMEOUT), section_url)

    def run_full_crawl(self, keywords=None):
        if keywords is None:
            keywords = [
                "droit", "loi", "code", "travail", "famille",
                "impôt", "commercial", "pénal", "civil", "foncier",
                "eau", "environnement", "investissement", "social",
                "éducation", "santé", "agriculture", "énergie",
            ]

        all_laws = []
        for keyword in keywords:
            laws = self.crawl_keyword(keyword)
            all_laws.extend(laws)

        all_laws.extend(self.crawl_textes_consolides())
        all_laws.extend(self.crawl_textes_importants())

        self.db.update_crawler_state(
            "SGG", "completed", len(all_laws)
        )
        return all_laws
