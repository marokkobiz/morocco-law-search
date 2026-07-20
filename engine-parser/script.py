import fitz
import pytesseract
from PIL import Image
import re
import os

from parser.cleaner import LegalTextCleaner
from parser.normalizer import ArabicTextNormalizer
from parser.exporter import save_meilisearch_json

pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'


def extract_brute_force_ocr(pdf_path: str) -> str:
    doc = fitz.open(pdf_path)
    output_lines = []
    for i, page in enumerate(doc):
        pix = page.get_pixmap(dpi=300)
        img = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
        text = pytesseract.image_to_string(img, lang="ara", config="--psm 6")
        if i > 0:
            output_lines.append('\f')
        output_lines.append(text)
    doc.close()
    return '\n'.join(output_lines)


def parse_ocr_articles(text: str) -> list:
    # Pattern to match Chapters, Articles, or Annex markers
    pattern = r"^\s*((الباب\s+[^\n]+)|(المادة\s+(الأولى|الاولى|الثانية|الثالثة|الرابعة|الخامسة|السادسة|السابعة|الثامنة|التاسعة|العاشرة|\d+))|(الملحق\s+.*))\s*$"
    
    matches = list(re.finditer(pattern, text, re.MULTILINE))
    
    extracted = []
    current_chapter = ""
    article_counter = 1
    
    for i, match in enumerate(matches):
        full_title = match.group(1).strip()
        
        # Stop at Annexes
        if full_title.startswith("الملحق"):
            break
            
        # Capture Chapter and store it in context
        if full_title.startswith("الباب"):
            current_chapter = full_title
            continue
            
        # Capture Article
        if full_title.startswith("المادة"):
            start_idx = match.end()
            end_idx = matches[i + 1].start() if i + 1 < len(matches) else len(text)
            
            article_num = match.group(4).strip()
            article_content = text[start_idx:end_idx].strip()
            
            # Remove any trailing Chapter headers trailing inside text body
            article_content = re.sub(r"\n\s*الباب\s+.*$", "", article_content).strip()
            
            path_str = f"{current_chapter} > {full_title}" if current_chapter else full_title
            
            extracted.append({
                "article": article_num,
                "article_num": article_num,
                "_sort_key": article_counter,
                "sort_key": article_counter,
                "breadcrumb": {"chapter": current_chapter} if current_chapter else {},
                "text": article_content,
                "path": path_str,
                "slug": f"article-{article_num}",
                "breadcrumb_chapter": current_chapter
            })
            article_counter += 1

    return extracted

def run_pipeline(pdf_path: str, document_meta: dict, output_file: str):
    print(f"🚀 Processing: {pdf_path}")
    print("  ↳ Rasterizing PDF to images and forcing OCR...")
    raw_text = extract_brute_force_ocr(pdf_path)

    print("  ↳ 🧹 Cleaning text...")
    cleaner = LegalTextCleaner()
    cleaned_text = cleaner.clean_text(raw_text)

    print("  ↳ ⚖️ Normalizing text...")
    normalizer = ArabicTextNormalizer()
    normalized_text = normalizer.normalize(cleaned_text)

    print("  ↳ 🏗️ Parsing structure...")
    articles = parse_ocr_articles(normalized_text)

    saved_count = save_meilisearch_json(articles, document_meta, output_file)

    print("======================")
    print(f"✅ ARTICLES EXTRACTED & SAVED: {saved_count}")
    print(f"📄 Output file: {output_file}")
    print("======================\n")


if __name__ == "__main__":
    # Test 1: Legal Time Decree (2 Pages)
    run_pipeline(
        pdf_path="tests/arabic/مرسوم رقم 2.26.530 في شأن الساعة القانونية-1783329642436.pdf",
        document_meta={
            "title": "مرسوم رقم 2.26.530 في شأن الساعة القانونية",
            "language": "ar",
            "type": "Décret",
            "source_file": "tests/arabic/مرسوم رقم 2.26.530 في شأن الساعة القانونية-1783329642436.pdf"
        },
        output_file="data/legal_time_decree_meilisearch.json"
    )

    # Test 2: Wheat Flour Joint Decision (8 Pages with Chapters & Annexes)
    run_pipeline(
        pdf_path="tests/arabic/قرار مشترك لوزير الداخلية والفلاحة والصيد البحري والتنمية القروية-1783338253932.pdf",
        document_meta={
            "title": "قرار مشترك بتحديد شروط شراء القمح اللين الموجه لصنع الدقيق المدعوم",
            "language": "ar",
            "type": "Arrêté conjoint",
            "source_file": "tests/arabic/قرار مشترك لوزير الداخلية والفلاحة والصيد البحري والتنمية القروية-1783338253932.pdf"
        },
        output_file="data/wheat_flour_joint_decision_meilisearch.json"
    )