import os
import re
from pdf2image import convert_from_path
import pytesseract
import pdfplumber
import arabic_reshaper
from bidi.algorithm import get_display

# =====================================================================
# 🛠️ WINDOWS CONFIGURATION (Modify paths to match where you saved them)
# =====================================================================
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'
POPPLER_PATH = r'C:\poppler\Library\bin' 
# =====================================================================

def contains_arabic(text):
    """Checks if the text contains Arabic characters."""
    return bool(re.search(r'[\u0600-\u06FF]', text))

def fix_reversed_arabic_text(raw_text):
    """
    Fixes raw Arabic text that was extracted backward/reversed 
    at the character level by digital PDF extractors.
    """
    cleaned_lines = []
    for line in raw_text.split('\n'):
        line = line.strip()
        if not line:
            cleaned_lines.append("")
            continue
            
        # Do not flip standard structural page boundary tags
        if line.startswith("---") and line.endswith("---"):
            cleaned_lines.append(line)
            continue
            
        # Reverse the text line characters to fix the LTR layout rendering bug
        reversed_line = line[::-1]
        
        # Keep numerical blocks, percentages, and punctuation properly oriented on flip
        reversed_line = re.sub(r'\s*(\d+[\.\d%]*)\s*', r' \1 ', reversed_line)
        reversed_line = reversed_line.replace(')', 'tmp_b').replace('(', ')').replace('tmp_b', '(')
        
        # Connect isolated shapes into natural cursive configurations
        reshaped = arabic_reshaper.reshape(reversed_line)
        logical_arabic = get_display(reshaped)
        cleaned_lines.append(logical_arabic)
        
    return "\n".join(cleaned_lines)

def process_with_ocr(pdf_path):
    """Converts PDF to high-res images and reads it visually via OCR."""
    print(f"   👁️ Visual OCR activated for better Arabic quality...")
    try:
        pages = convert_from_path(pdf_path, dpi=300, poppler_path=POPPLER_PATH)
        ocr_content = []
        
        for i, page_img in enumerate(pages, start=1):
            raw_text = pytesseract.image_to_string(page_img, lang='ara+fra')
            
            if contains_arabic(raw_text):
                reshaped = arabic_reshaper.reshape(raw_text)
                formatted_text = get_display(reshaped)
            else:
                formatted_text = raw_text
                
            ocr_content.append(f"\n--- PAGE {i} ---\n" + formatted_text)
            
        return "".join(ocr_content)
    except Exception as e:
        print(f"   ⚠️ OCR Environment bypassed/failed. Falling back to direct digital stream processing...")
        return None

def process_pdf_folder(input_folder, output_folder):
    if not os.path.exists(output_folder):
        os.makedirs(output_folder)

    pdf_files = [f for f in os.listdir(input_folder) if f.lower().endswith('.pdf')]
    if not pdf_files:
        print(f"No PDFs found in '{input_folder}'.")
        return

    print(f"Found {len(pdf_files)} PDF(s) to process.\n" + "-"*40)

    for pdf_file in pdf_files:
        pdf_path = os.path.join(input_folder, pdf_file)
        txt_filename = os.path.splitext(pdf_file)[0] + ".txt"
        txt_path = os.path.join(output_folder, txt_filename)

        print(f"Processing: {pdf_file}...")
        
        try:
            # Step 1: Extract raw text directly from the digital layer
            extracted_content = []
            with pdfplumber.open(pdf_path) as pdf:
                for i, page in enumerate(pdf.pages, start=1):
                    text = page.extract_text() or ""
                    extracted_content.append(f"\n--- PAGE {i} ---\n" + text)
            
            raw_document_text = "".join(extracted_content)
            
            # Step 2: Determine if it's scrambled Arabic or standard French
            if contains_arabic(raw_document_text):
                print(f"   Detected digital Arabic layer. Reversing character layouts...")
                full_text = fix_reversed_arabic_text(raw_document_text)
            elif not raw_document_text.strip():
                # If the PDF is completely blank (scanned image only), try OCR
                full_text = process_with_ocr(pdf_path)
            else:
                # Standard French/English digital document
                print(f"   📄 Standard Western document layout detected.")
                full_text = raw_document_text

            # Step 3: Save results cleanly
            if full_text:
                with open(txt_path, "w", encoding="utf-8") as f:
                    f.write(full_text)
                print(f"   💾 Saved clean text to: {txt_filename}\n")

        except Exception as e:
            print(f"   ❌ Error processing {pdf_file}: {e}\n")

if __name__ == "__main__":
    INPUT_DIR = "./downloaded_laws/"  # Folder where you drop your Adala PDFs
    OUTPUT_DIR = "./extracted_laws"    

    if not os.path.exists(INPUT_DIR):
        os.makedirs(INPUT_DIR)
        print(f"Created '{INPUT_DIR}' folder. Drop your Adala PDFs there and re-run!")
    else:
        process_pdf_folder(INPUT_DIR, OUTPUT_DIR)