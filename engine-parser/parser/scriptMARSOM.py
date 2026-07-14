import fitz
import pytesseract
from PIL import Image
import re
from parser.cleaner import LegalTextCleaner
from parser.normalizer import ArabicTextNormalizer
# Import our new Meilisearch exporter!
from parser.exporter import save_meilisearch_json

pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# We will change this variable for every new PDF we test
PDF = "tests/arabic/مرسوم رقم 2.26.530 في شأن الساعة القانونية-1783329642436.pdf"

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
    return '\n'.join(output_lines)

def parse_ocr_articles(text: str) -> list:
    # Added ^ and $ to ensure it only matches headers on their own line!
    pattern = r"^\s*(المادة\s+(الأولى|الاولى|الثانية|الثالثة|الرابعة|الخامسة|السادسة|السابعة|الثامنة|التاسعة|العاشرة|\d+))\s*$"
    
    # re.MULTILINE is critical here so ^ works on every new line
    matches = list(re.finditer(pattern, text, re.MULTILINE))
    
    extracted = []
    for i, match in enumerate(matches):
        start_idx = match.end()
        end_idx = matches[i+1].start() if i + 1 < len(matches) else len(text)
        
        article_title = match.group(1).strip()
        article_num = match.group(2).strip()
        article_content = text[start_idx:end_idx].strip()
        
        extracted.append({
            "article": article_num,
            "_sort_key": i + 1,
            "breadcrumb": {},
            "text": article_content,
            "path": article_title,
            "slug": f"article-{article_num}",
        })
    return extracted

print("🚀 Rasterizing PDF to images and forcing OCR...")
raw_text = extract_brute_force_ocr(PDF)

print("🧹 Cleaning text...")
cleaner = LegalTextCleaner()
cleaned_text = cleaner.clean_text(raw_text)

print("⚖️  Normalizing text...")
normalizer = ArabicTextNormalizer()
normalized_text = normalizer.normalize(cleaned_text)

print("🏗️  Parsing structure...")
articles = parse_ocr_articles(normalized_text)

# Set the metadata for this specific document
document_meta = {
    "title": "مرسوم رقم 2.26.530 في شأن الساعة القانونية",
    "language": "ar",
    "type": "Décret",
    "source_file": PDF
}

# Export directly to the Meilisearch format
output_file = "data/legal_time_decree_meilisearch.json"
saved_count = save_meilisearch_json(articles, document_meta, output_file)

print("\n======================")
print(f"✅ ARTICLES EXTRACTED & SAVED: {saved_count}")
print(f"📄 File ready for Teammate: {output_file}")
print("======================\n")