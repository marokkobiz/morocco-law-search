import os
import json
import re
import hashlib
from pathlib import Path
from docling.document_converter import DocumentConverter, PdfFormatOption
from docling.datamodel.base_models import InputFormat
from docling.datamodel.pipeline_options import PdfPipelineOptions
from docling.backend.pypdfium2_backend import PyPdfiumDocumentBackend

def setup_converter(use_ocr: bool = False) -> DocumentConverter:
    """Sets up the converter with OCR capability for scanned documents."""
    pipeline_options = PdfPipelineOptions()
    pipeline_options.generate_page_images = False
    pipeline_options.generate_picture_images = False
    pipeline_options.do_table_structure = False
    pipeline_options.do_ocr = use_ocr
        
    return DocumentConverter(
        format_options={
            InputFormat.PDF: PdfFormatOption(
                pipeline_options=pipeline_options,
                backend=PyPdfiumDocumentBackend
            )
        }
    )

def detect_language(text: str) -> str:
    return "ar" if re.search(r'[\u0600-\u06FF]', text) else "fr"

def detect_doc_type(title: str) -> str:
    title_lower = title.lower()
    if any(x in title_lower for x in ["مرسوم", "décret"]): return "Décret"
    if any(x in title_lower for x in ["ظهير", "dahir"]): return "Dahir"
    if any(x in title_lower for x in ["قرار", "arrêté"]): return "Arrêté"
    if any(x in title_lower for x in ["قانون", "loi"]): return "Loi"
    return "Autre"

def generate_deterministic_id(title: str, article_num: str) -> str:
    return f"doc_{hashlib.md5(f'{title}_{article_num}'.encode('utf-8')).hexdigest()[:8]}"

def parse_single_pdf(pdf_path: str, converter: DocumentConverter) -> list:
    try:
        result = converter.convert(pdf_path)
        doc = result.document
    except Exception as e:
        print(f"❌ Error reading {pdf_path}: {e}")
        return []
    
    doc_source_file = str(Path(pdf_path).as_posix())
    doc_title = Path(pdf_path).stem
    
    articles = []
    current_article = None
    sort_key = 1
    
    # Greedy Regex patterns
    ar_pattern = re.compile(r'^(المادة|الفصل)\s+([0-9\u0627-\u064a]+)', re.IGNORECASE)
    fr_pattern = re.compile(r'^(Article|Art\.)\s+([0-9a-z]+)', re.IGNORECASE)
    
    # ⚡ Use iterate_items() as it is the standard way to traverse Docling documents
    for item, _ in doc.iterate_items():
        text = getattr(item, "text", None)
        if not text:
            continue
            
        text = text.strip()
        is_ar = ar_pattern.match(text)
        is_fr = fr_pattern.match(text)
        
        if is_ar or is_fr:
            if current_article:
                current_article["text"] = "\n".join(current_article["text_chunks"]).strip()
                del current_article["text_chunks"]
                articles.append(current_article)
            
            match = is_ar if is_ar else is_fr
            article_num = match.group(2)
            
            current_article = {
                "doc_title": doc_title,
                "doc_language": detect_language(text),
                "doc_type": detect_doc_type(doc_title),
                "doc_source_file": doc_source_file,
                "id": generate_deterministic_id(doc_title, article_num),
                "article_num": article_num,
                "sort_key": sort_key,
                "text_chunks": [],
                "path": text,
                "slug": f"article-{article_num}"
            }
            sort_key += 1
        elif current_article is not None:
            current_article["text_chunks"].append(text)
                
    if current_article:
        current_article["text"] = "\n".join(current_article["text_chunks"]).strip()
        del current_article["text_chunks"]
        articles.append(current_article)
        
    return articles

if __name__ == "__main__":
    fast_conv = setup_converter(use_ocr=False)
    ocr_conv = setup_converter(use_ocr=True)
    
    pdf_dir = "./downloaded_laws"
    all_articles = []
    
    pdf_files = [os.path.join(pdf_dir, f) for f in os.listdir(pdf_dir) if f.endswith(".pdf")]
    
    for pdf in pdf_files:
        print(f"Parsing: {Path(pdf).name}...")
        results = parse_single_pdf(pdf, fast_conv)
        
        # Auto-OCR Fallback[cite: 3]
        if not results:
            print("   ⚠️ Empty parse, re-trying with OCR...")
            results = parse_single_pdf(pdf, ocr_conv)
            
        all_articles.extend(results)
        
        # Live Save[cite: 3]
        with open("test_law.json", "w", encoding="utf-8") as f:
            json.dump(all_articles, f, ensure_ascii=False, indent=2)
            
    print(f"🎉 Final count: {len(all_articles)} articles extracted.")