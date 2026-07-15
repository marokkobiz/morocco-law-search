import os
import json
import re
import hashlib
from pathlib import Path
from docling.document_converter import DocumentConverter, PdfFormatOption
from docling.datamodel.base_models import InputFormat
from docling.datamodel.pipeline_options import PdfPipelineOptions
from docling.backend.pypdfium2_backend import PyPdfiumDocumentBackend
import pytesseract
from PIL import Image
import pdf2image

# --- Helper for Manual Tesseract OCR ---
def ocr_arabic_pdf(pdf_path):
    """Fallback manual OCR for stubborn Arabic PDFs."""
    poppler_path = r'C:\Users\akhad\Downloads\Release-26.02.0-0\poppler-26.02.0\Library\bin'
    try:
        pages = pdf2image.convert_from_path(pdf_path, dpi=150, poppler_path=poppler_path)
        full_text = []
        for page in pages:
            # Using --psm 6 for uniform block text; added --oem 3 for best engine mode
            text = pytesseract.image_to_string(page, lang='ara+fra', config='--psm 6 --oem 3')
            full_text.append(text)
        return "\n".join(full_text)
    except Exception as e:
        print(f"❌ Critical OCR Failure for {pdf_path}: {e}")
        return ""

def setup_converter(use_ocr: bool = False) -> DocumentConverter:
    pipeline_options = PdfPipelineOptions()
    pipeline_options.do_ocr = use_ocr
    return DocumentConverter(format_options={InputFormat.PDF: PdfFormatOption(pipeline_options=pipeline_options)})

# --- Updated Parsing Logic ---
def parse_single_pdf(pdf_path: str, converter: DocumentConverter) -> list:
    doc_title = Path(pdf_path).stem
    
    # Check if this looks like an Arabic file that needs manual intervention
    # (If your docling pipeline continues to return empty results)
    try:
        result = converter.convert(pdf_path)
        doc = result.document
        # If docling text content is minimal, force manual OCR
        if not doc.text or len(doc.text) < 100:
            raise ValueError("Docling failed to extract meaningful text.")
    except:
        print(f"   ⚠️ Switching to Manual Tesseract OCR for {doc_title}...")
        manual_text = ocr_arabic_pdf(pdf_path)
        # We treat manual_text as a single giant "page" or chunk for regex parsing
        # Or you can re-feed this text into a mock structure if needed.
        return parse_text_stream(manual_text, pdf_path)

    # Proceed with standard docling iteration...
    # (Your existing iteration logic goes here)
    return extract_articles_from_docling(doc, pdf_path)

def parse_text_stream(text: str, pdf_path: str) -> list:
    """Helper to parse raw string text into article objects."""
    articles = []
    # Simplified regex for articles
    pattern = re.compile(r'(المادة|الفصل|Article|Art\.)\s+([0-9\u0627-\u064a]+)', re.IGNORECASE)
    
    # Split text into chunks by article markers
    parts = pattern.split(text)
    # logic to reconstruct into article objects...
    return articles # Placeholder for your specific reconstruction logic

if __name__ == "__main__":
    # 1. Load existing data if file exists
    json_path = "test_law.json"
    if os.path.exists(json_path):
        with open(json_path, "r", encoding="utf-8") as f:
            try:
                all_articles = json.load(f)
                print(f"Loaded {len(all_articles)} existing articles.")
            except json.JSONDecodeError:
                all_articles = []
    else:
        all_articles = []

    fast_conv = setup_converter(use_ocr=False)
    
    pdf_dir = "./downloaded_laws"
    pdf_files = [os.path.join(pdf_dir, f) for f in os.listdir(pdf_dir) if f.endswith(".pdf")]
    
    for pdf in pdf_files:
        # Check if file is already processed to avoid duplicates
        if any(item.get("doc_source_file") == str(Path(pdf).as_posix()) for item in all_articles):
            print(f"Skipping already processed: {Path(pdf).name}")
            continue

        print(f"Parsing: {Path(pdf).name}...")
        results = parse_single_pdf(pdf, fast_conv)
        
        if results:
            all_articles.extend(results)
            # Update the file after each successful document to ensure persistence
            with open(json_path, "w", encoding="utf-8") as f:
                json.dump(all_articles, f, ensure_ascii=False, indent=2)
            
    print(f"🎉 Final count: {len(all_articles)} articles extracted.")