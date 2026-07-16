import os
os.environ['TESSDATA_PREFIX'] = r'C:\Program Files\Tesseract-OCR\tessdata'
import json
import re
import hashlib
from pathlib import Path

from docling.datamodel.pipeline_options import PdfPipelineOptions
from docling.document_converter import DocumentConverter, PdfFormatOption
from docling.datamodel.base_models import InputFormat
from docling.datamodel.pipeline_options import PdfPipelineOptions
from docling.backend.pypdfium2_backend import PyPdfiumDocumentBackend
import pytesseract
from PIL import Image
import pdf2image



pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# --- Helper for Manual Tesseract OCR ---
def ocr_arabic_pdf(pdf_path):
    """Fallback manual OCR for stubborn Arabic PDFs."""
    poppler_path = r'C:\poppler\Library\bin'
    try:
        pages = pdf2image.convert_from_path(pdf_path, dpi=100, poppler_path=poppler_path)
        full_text = []
        for page in pages:
            # Using --psm 6 for uniform block text; added --oem 3 for best engine mode
            text = pytesseract.image_to_string(page, lang='ara+fra', config='--psm 6 --oem 3')
            print(f"DEBUG: OCR extracted {len(text)} characters from page.") # ADD THIS
   
            full_text.append(text)
        return "\n".join(full_text)
    except Exception as e:
        print(f"❌ Critical OCR Failure for {pdf_path}: {e}")
        return ""

def setup_converter(use_ocr: bool = False) -> DocumentConverter:
    pipeline_options = PdfPipelineOptions()
    pipeline_options.do_ocr = use_ocr
    # If the import above fails, just delete this line and leave only the options below
    return DocumentConverter(format_options={InputFormat.PDF: PdfFormatOption(pipeline_options=pipeline_options)})

# --- Updated Parsing Logic ---
# Create a small list to remember which files crashed previously
failed_files = [] 

def parse_single_pdf(pdf_path: str, converter: DocumentConverter) -> list:
    doc_title = Path(pdf_path).name
    
    # If the file is known to be problematic, skip Docling entirely
    if doc_title in failed_files:
        print(f"   ⏩ Skipping Docling for known heavy file: {doc_title}")
        return parse_text_stream(ocr_arabic_pdf(pdf_path), pdf_path)

    try:
        result = converter.convert(pdf_path)
        full_text = result.document.export_to_text()
        
        if not full_text or len(full_text) < 100:
            raise ValueError("No text found")
            
        return parse_text_stream(full_text, pdf_path)
        
    except Exception as e:
        print(f"   ⚠️ Memory/Docling failure for {doc_title}, adding to failed list and switching to Manual OCR...")
        failed_files.append(doc_title) # Remember this file for the future
        return parse_text_stream(ocr_arabic_pdf(pdf_path), pdf_path)
    
def parse_text_stream(text: str, pdf_path: str) -> list:
    articles = []
    # Updated regex to match "المادة" OR "Article" (case-insensitive)
    pattern = re.compile(r'(المادة\s+\d+|Article\s+\d+)', re.IGNORECASE)
    
    chunks = pattern.split(text)
    
    for i in range(1, len(chunks), 2):
        if i + 1 < len(chunks):
            # Clean header to get just the number
            header = chunks[i]
            article_num = re.sub(r'(المادة|Article)', '', header, flags=re.IGNORECASE).strip()
            
            article_data = {
                "article_num": article_num,
                "text": chunks[i+1].strip()[:1000],
                "doc_source_file": str(Path(pdf_path).as_posix()),
                "doc_language": "ar" if "المادة" in header else "fr"
            }
            articles.append(article_data)
    return articles

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
        import gc
        gc.collect()
            
    print(f"🎉 Final count: {len(all_articles)} articles extracted.")