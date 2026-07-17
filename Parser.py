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
# 1. Path to your Tesseract executable
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# 2. Path to your Poppler bin folder
POPPLER_PATH = r'C:\poppler\Library\bin' 
# =====================================================================

def contains_arabic(text):
    """Checks if the text contains Arabic characters."""
    return bool(re.search(r'[\u0600-\u06FF]', text))

def process_with_ocr(pdf_path):
    """Converts PDF to high-res images and reads it visually via OCR."""
    print(f"   👁️ Visual OCR activated for better Arabic quality...")
    try:
        # Pass the poppler_path variable directly here!
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
        print(f"   ❌ OCR Failed: {e}. Falling back to basic text pull.")
        return None

# ... (The rest of your process_pdf_folder and main execution blocks stay exactly the same)

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
        
        # Step 1: Try a quick check with pdfplumber first
        try:
            with pdfplumber.open(pdf_path) as pdf:
                first_page_text = pdf.pages[0].extract_text() or ""
            
            # Step 2: If it's Arabic, or empty, use OCR for high-quality text extraction
            if contains_arabic(first_page_text) or not first_page_text.strip():
                full_text = process_with_ocr(pdf_path)
            else:
                # If it's standard French digital text, extract it normally (much faster)
                print(f"   📄 Standard French document detected. Extracting text directly...")
                extracted_content = []
                with pdfplumber.open(pdf_path) as pdf:
                    for i, page in enumerate(pdf.pages, start=1):
                        text = page.extract_text() or ""
                        extracted_content.append(f"\n--- PAGE {i} ---\n" + text)
                full_text = "".join(extracted_content)

            # Step 3: Save results
            if full_text:
                with open(txt_path, "w", encoding="utf-8") as f:
                    f.write(full_text)
                print(f"   💾 Saved clean text to: {txt_filename}\n")

        except Exception as e:
            print(f"   ❌ Error processing {pdf_file}: {e}\n")

if __name__ == "__main__":
    INPUT_DIR = "./adalajustice_pdfs"  
    OUTPUT_DIR = "./extracted_laws"    

    if not os.path.exists(INPUT_DIR):
        os.makedirs(INPUT_DIR)
        print(f"Created '{INPUT_DIR}' folder. Drop your Adala PDFs there and re-run!")
    else:
        process_pdf_folder(INPUT_DIR, OUTPUT_DIR)