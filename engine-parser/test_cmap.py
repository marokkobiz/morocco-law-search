import fitz
from fontTools.ttLib import TTFont
import io

pdf_path = "tests/arabic/مرسوم رقم 2.26.530 في شأن الساعة القانونية-1783329642436.pdf"
doc = fitz.open(pdf_path)

print("🔍 PEEKING INTO THE FONT FILE...\n")

for page in doc:
    for font in page.get_fonts(full=True):
        xref = font[0]
        try:
            font_bytes = doc.xref_stream(xref)
            ttfont = TTFont(io.BytesIO(font_bytes))
            
            for table in ttfont['cmap'].tables:
                if table.isUnicode():
                    print(f"--- FONT XREF {xref} ---")
                    count = 0
                    for code, name in table.cmap.items():
                        if 0xE000 <= code <= 0xF8FF:
                            print(f"PUA Code: {hex(code)}  -->  Secret Glyph Name: {name}")
                            count += 1
                            if count >= 15: # Just need a sample of 15
                                break
                    if count > 0:
                        break
        except Exception as e:
            pass
    break # Just need the first page