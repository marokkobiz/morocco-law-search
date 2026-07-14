import fitz
from fontTools.ttLib import TTFont
import io
import re

class DynamicFontDecoder:
    def __init__(self, pdf_path: str):
        self.pdf_path = pdf_path
        self.pua_map = self._build_dynamic_pua_map()

    def _build_dynamic_pua_map(self) -> dict:
        """
        Extracts embedded fonts, reads their CMAP tables, and maps 
        Private Use Area (PUA) codes dynamically to standard Arabic Unicode.
        """
        doc = fitz.open(self.pdf_path)
        pua_to_unicode = {}
        
        for page in doc:
            for font in page.get_fonts(full=True):
                xref = font[0]
                try:
                    font_bytes = doc.xref_stream(xref)
                    if not font_bytes:
                        continue
                        
                    # Load TTF from memory without saving to disk
                    ttfont = TTFont(io.BytesIO(font_bytes))
                    if 'cmap' not in ttfont:
                        continue
                        
                    for table in ttfont['cmap'].tables:
                        if table.isUnicode():
                            for code, glyph_name in table.cmap.items():
                                # Only care about PUA range (U+E000 to U+F8FF)
                                if 0xE000 <= code <= 0xF8FF:
                                    real_char = self._decode_glyph_name(glyph_name)
                                    if real_char:
                                        pua_to_unicode[chr(code)] = real_char
                except Exception as e:
                    # Skip gracefully if the embedded font isn't a standard TTF
                    pass 
                    
        return pua_to_unicode

    def _decode_glyph_name(self, glyph_name: str) -> str:
        """Converts a TTF glyph name into a standard Unicode character."""
        # 1. Handle 'uniXXXX' format (e.g., 'uni0627' -> 'ا')
        if glyph_name.startswith('uni') and len(glyph_name) >= 7:
            try:
                hex_str = glyph_name[3:7]
                return chr(int(hex_str, 16))
            except ValueError:
                pass
                
        # 2. Handle 'uXXXX' format
        if re.match(r'^u[0-9A-Fa-f]{4}$', glyph_name):
            try:
                return chr(int(glyph_name[1:5], 16))
            except ValueError:
                pass
        
        return None

    def fix_text(self, text: str) -> str:
        """Applies the dynamic dictionary to a string of text."""
        if not text:
            return text
            
        # Translate the string using our custom map
        translation_table = str.maketrans(self.pua_map)
        return text.translate(translation_table)