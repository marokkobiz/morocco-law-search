import os
import json
import re
import hashlib
from pathlib import Path
from docling.document_converter import DocumentConverter, PdfFormatOption
from docling.datamodel.base_models import InputFormat
from docling.datamodel.pipeline_options import PdfPipelineOptions
from docling.backend.pypdfium2_backend import PyPdfiumDocumentBackend
from docling_core.types.doc import DocItemLabel

def setup_converter(use_ocr: bool = False) -> DocumentConverter:
    """Sets up the converter. Can turn OCR on dynamically for scanned files."""
    pipeline_options = PdfPipelineOptions()
    pipeline_options.generate_page_images = False
    pipeline_options.generate_picture_images = False
    pipeline_options.do_table_structure = False
    
    if use_ocr:
        pipeline_options.do_ocr = True
        print("   🔍 OCR Enabled for this file (processing images/scans)...")
    else:
        pipeline_options.do_ocr = False
        
    return DocumentConverter(
        format_options={
            InputFormat.PDF: PdfFormatOption(
                pipeline_options=pipeline_options,
                backend=PyPdfiumDocumentBackend
            )
        }
    )

def detect_language(text: str) -> str:
    if re.search(r'[\u0600-\u06FF]', text):
        return "ar"
    return "fr"

def detect_doc_type(title: str) -> str:
    title_lower = title.lower()
    if "مرسوم" in title_lower or "décret" in title_lower:
        return "Décret"
    elif "ظهير" in title_lower or "dahir" in title_lower:
        return "Dahir"
    elif "قرار" in title_lower or "arrêté" in title_lower:
        return "Arrêté"
    elif "قانون" in title_lower or "loi" in title_lower:
        return "Loi"
    return "Autre"

def generate_deterministic_id(title: str, article_num: str) -> str:
    unique_string = f"{title}_{article_num}"
    md5_hash = hashlib.md5(unique_string.encode('utf-8')).hexdigest()
    return f"doc_{md5_hash[:8]}"

def parse_single_pdf(pdf_path: str, converter: DocumentConverter) -> list:
    try:
        result = converter.convert(pdf_path)
        doc = result.document
    except Exception as e:
        print(f"❌ Error reading {pdf_path}: {e}")
        return []
    
    doc_source_file = str(Path(pdf_path).as_posix())
    
    # Safely find the first heading
    first_heading = ""
    for item, level in doc.iterate_items():
        label = getattr(item, "label", None)
        text_val = getattr(item, "text", None)
        if text_val and label == DocItemLabel.SECTION_HEADER:
            first_heading = text_val.strip()
            break
    
    doc_title = first_heading if first_heading else Path(pdf_path).stem
    
    # Safely extract text previews
    preview_chunks = []
    for item, _ in doc.iterate_items():
        text_val = getattr(item, "text", None)
        if text_val:
            preview_chunks.append(text_val)
            if len(preview_chunks) >= 5:
                break
                
    all_text_preview = "".join(preview_chunks)
    doc_language = detect_language(all_text_preview or doc_title)
    doc_type = detect_doc_type(doc_title)
    
    articles = []
    current_article = None
    sort_key = 1
    current_chapter = ""
    
    # ⚡ ENHANCED REGEX PATTERNS for Moroccan laws:
    # Arabic matches "المادة الأولى", "المادة 1", "الفصل 1", "الفصل الأول", etc.
    ar_pattern = re.compile(
        r'^(المادة|الفصل)\s+(ال[أا]ولى|ال[أا]ول|الثانية|الثاني|الثالثة|الثالث|الرابعة|الرابع|الخامسة|الخامس|السادسة|السادس|السابعة|السابع|الثامنة|الثامن|التاسعة|التاسع|العاشرة|العاشر|الفريد|الفريدة|\d+)', 
        re.IGNORECASE
    )
    # French matches "Article 1", "Article premier", "Article unique", "Art. 1", etc.
    fr_pattern = re.compile(
        r'^(Article|Art\.)\s+(premier|première|unique|\d+)', 
        re.IGNORECASE
    )
    
    for item, _ in doc.iterate_items():
        text_val = getattr(item, "text", None)
        if not text_val:
            continue
            
        text = text_val.strip()
        label = getattr(item, "label", None)
        
        # Capture context chapters ("الباب الأول", "Chapitre I")
        if label == DocItemLabel.SECTION_HEADER and not ar_pattern.match(text) and not fr_pattern.match(text):
            if "باب" in text.lower() or "chapitre" in text.lower() or "قسم" in text.lower():
                current_chapter = text
                continue

        is_ar_article = ar_pattern.match(text)
        is_fr_article = fr_pattern.match(text)
        
        if is_ar_article or is_fr_article:
            if current_article:
                current_article["text"] = "\n".join(current_article["text_chunks"]).strip()
                del current_article["text_chunks"]
                articles.append(current_article)
            
            match = is_ar_article if is_ar_article else is_fr_article
            article_num = match.group(2)
            
            current_article = {
                "doc_title": doc_title,
                "doc_language": doc_language,
                "doc_type": doc_type,
                "doc_source_file": doc_source_file,
                "id": generate_deterministic_id(doc_title, article_num),
                "article_num": article_num,
                "sort_key": sort_key,
                "text_chunks": [],
                "path": text,
                "slug": f"article-{article_num}",
                "breadcrumb_chapter": current_chapter
            }
            sort_key += 1
        else:
            if current_article is not None:
                current_article["text_chunks"].append(text)
                
    if current_article:
        current_article["text"] = "\n".join(current_article["text_chunks"]).strip()
        del current_article["text_chunks"]
        articles.append(current_article)
        
    return articles

if __name__ == "__main__":
    # 1. Setup default fast converter (No OCR)
    fast_converter = setup_converter(use_ocr=False)
    ocr_converter = None  # Lazy load only if needed
    
    pdf_directory = "./downloaded_laws" 
    output_json_path = "test_law.json"
    
    os.makedirs(pdf_directory, exist_ok=True)
    
    pdf_files = [
        os.path.join(pdf_directory, f) 
        for f in os.listdir(pdf_directory) 
        if f.lower().endswith(".pdf")
    ]
    
    if not pdf_files:
        print(f"No PDF files found in '{pdf_directory}'.")
    else:
        print(f"Found {len(pdf_files)} PDF(s) to parse. Processing...")
        
        all_articles = []
        
        for index, pdf in enumerate(pdf_files, 1):
            print(f"[{index}/{len(pdf_files)}] Parsing: {pdf}...")
            
            # Try parsing with fast converter first
            file_articles = parse_single_pdf(pdf, fast_converter)
            
            # Auto-OCR Fallback: If 0 articles found, try again with OCR on
            if len(file_articles) == 0:
                print("   ⚠️ No text or articles found. Re-trying with OCR...")
                if ocr_converter is None:
                    ocr_converter = setup_converter(use_ocr=True)
                file_articles = parse_single_pdf(pdf, ocr_converter)
                
            all_articles.extend(file_articles)
            print(f"   ↳ Done! Extracted {len(file_articles)} article(s).")
            
            # 🟢 SAVE PROGRESS IMMEDIATELY: Write to JSON file right now
            with open(output_json_path, "w", encoding="utf-8") as f:
                json.dump(all_articles, f, ensure_ascii=False, indent=2)
            print(f"   💾 Progress saved to '{output_json_path}'\n")
            
        print(f"\n🎉 All done! Extracted {len(all_articles)} total articles.")
        print(f"Final results are safe and sound in: {output_json_path}")