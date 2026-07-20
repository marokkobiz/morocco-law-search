
import re
try:
    from liteparse import LiteParse
except ImportError:
    raise ImportError(
        "'liteparse' is required.\n"
        "Install it using:\n"
        "pip install liteparse"
            )


# ─────────────────────────────────────────────────────────────
# Unicode bidi cleanup & Normalization
# ─────────────────────────────────────────────────────────────
_BIDI = '\u200e\u200f\u202a\u202b\u202c\u202d\u202e\u2066\u2067\u2068\u2069\ufeff'
_BIDI_TABLE = str.maketrans('', '', _BIDI)

# Map Eastern Arabic Numerals to Western for consistent parsing
EASTERN_TO_WESTERN = {
    ord('٠'): '0', ord('١'): '1', ord('٢'): '2', ord('٣'): '3', ord('٤'): '4',
    ord('٥'): '5', ord('٦'): '6', ord('٧'): '7', ord('٨'): '8', ord('٩'): '9',
    ord('٫'): '.', ord('٬'): ',',
}


def normalize_digits(text: str) -> str:
    """Convert Eastern Arabic Numerals to Western."""
    return text.translate(EASTERN_TO_WESTERN)


def strip_bidi(text: str) -> str:
    """Remove bidirectional control characters."""
    return text.translate(_BIDI_TABLE)


def clean(line: str) -> str:
    """Clean a line of text: strip bidi, normalize digits, collapse whitespace."""
    line = strip_bidi(line)
    line = normalize_digits(line)
    return re.sub(r'[ \t]+', ' ', line).strip()


# ─────────────────────────────────────────────────────────────
# Arabic ordinal → integer (for sorting headers)
# ─────────────────────────────────────────────────────────────
AR_ORDINAL = {
    'الأول': 1,   'الاول': 1,   'أول': 1,
    'الثاني': 2,  'الثانى': 2,  'ثاني': 2,
    'الثالث': 3,  'ثالث': 3,
    'الرابع': 4,  'رابع': 4,
    'الخامس': 5,  'خامس': 5,
    'السادس': 6,  'سادس': 6,
    'السابع': 7,  'سابع': 7,
    'الثامن': 8,  'ثامن': 8,
    'التاسع': 9,  'تاسع': 9,
    'العاشر': 10, 'عاشر': 10,
    'الحادي عشر': 11, 'الحادى عشر': 11,
    'الثاني عشر': 12, 'الثانى عشر': 12,

    # Feminine forms (common in legal Arabic)
    'الأولى': 1,  'الاولى': 1,
    'الثانية': 2, 'الثانيه': 2,
    'الثالثة': 3, 'الثالثه': 3,
    'الرابعة': 4, 'الرابعه': 4,
    'الخامسة': 5, 'الخامسه': 5,
    'السادسة': 6, 'السادسه': 6,
    'السابعة': 7, 'السابعه': 7,
    'الثامنة': 8, 'الثامنه': 8,
    'التاسعة': 9, 'التاسعه': 9,
    'العاشرة': 10, 'العاشرة': 10,
}


def ordinal_sort_key(ordinal: str) -> int:
    """Return an integer sort key for an Arabic ordinal string."""
    ordinal = ordinal.strip()
    if ordinal in AR_ORDINAL:
        return AR_ORDINAL[ordinal]

    # Check for compound ordinals
    for key, val in AR_ORDINAL.items():
        if key in ordinal:
            return val

    # Try digits
    m = re.search(r'\d+', ordinal)
    return int(m.group()) if m else 99


# ─────────────────────────────────────────────────────────────
# Hierarchy definition
# ─────────────────────────────────────────────────────────────
# Order matters: Longer/more specific phrases first.
# Levels: 6 (Book) > 5 (Part) > 4 (Title/Section) > 3 (Chapter) > 2 (Sub-chapter) > 0 (Article)

HIERARCHY_TOKENS = [
    ('الكتاب',        6, 'book'),
    ('الجزء',         5, 'part'),
    # Common in Moroccan codes between Part and Chapter
    ('العنوان',       4, 'title'),
    ('القسم',         4, 'section'),
    ('الباب',         3, 'chapter'),
    # In Penal Code, 'Fasl' is often a division, NOT an article
    ('الفصل',         2, 'sub_chapter'),
    # The actual Article (with definite article)
    ('المادة',        0, 'article'),
    ('املادة',        0, 'article'),     # Ligature variant
    # Bare form (common in Moroccan Penal Code)
    ('مادة',          0, 'article'),
]

# Regex to detect structural headers (Keyword + Ordinal Number/Word)
STRUCTURAL_HEADER_RE = re.compile(
    r'^(الكتاب|الجزء|العنوان|القسم|الباب|الفصل)\s+'
    r'(?:'
    r'(?:الأول|الاول|الأولى|الثاني|الثانى|الثانية|الثالث|الرابع|الخامس|السادس|السابع|الثامن|التاسع|العاشر|الحادي عشر|الثاني عشر)'
    r'|'
    r'(?:\d+)'
    r')'
)


# ─────────────────────────────────────────────────────────────
# Article number parsing
# ─────────────────────────────────────────────────────────────
MAX_ARTICLE_NUM = 1000


def parse_article_number(token: str) -> tuple[int | None, str | None]:
    """
    Parse the number portion after 'المادة'.
    Handles: plain integers, hyphenated sub-articles, footnote-concatenated numbers.
    Returns (None, None) if not parseable.
    """
    token = token.strip()
    if not token:
        return None, None

    # Remove common suffixes
    token = token.rstrip(':').rstrip('-').strip()

    # Try direct integer conversion
    try:
        n = int(token)
        if 1 <= n <= MAX_ARTICLE_NUM:
            return n, token
    except ValueError:
        pass

    # Handle "Mada X bis" variants - extract digit
    m = re.search(r'(\d+)', token)
    if m:
        n = int(m.group(1))
        if 1 <= n <= MAX_ARTICLE_NUM:
            return n, token

    return None, None


# ─────────────────────────────────────────────────────────────
# Line classifier
# ─────────────────────────────────────────────────────────────
NOISE_FRAGMENTS = [
    'وحدة الدراسات', 'رئاسة النيابة', 'اململكة', 'المملكة المغربية',
    'وحدة التوئتق', 'توئتق', 'الأمانة العامة للحكومة', 'امانة عامة',
    'الأمانة العامة', 'الامانة العامة', 'الجريدة الرسمية', 'عدد',
    'مرسوم', 'ظهير شريف', 'قانون رقم', 'صفحة', 'ص', '١', '٢', '٣'
]


def is_noise(line: str) -> bool:
    """Check if a line is noise (headers, footers, page numbers)."""
    if not line or len(line.strip()) < 3:
        return True
    # Ignore pure page numbers
    if re.match(r'^\d+$', line.strip()):
        return True
    for frag in NOISE_FRAGMENTS:
        if frag in line:
            return True
    return False


def classify_line(line: str) -> tuple[str | None, int, str | None, str | None]:
    """
    Classify a cleaned line.
    Returns: (keyword, level, json_key, remainder_text)
    Returns (None, -1, None, None) if not a hierarchy line.
    """
    if is_noise(line):
        return None, -1, None, None

    # 1. Check for Structural Headers (Book, Part, Chapter, etc.)
    m_struct = STRUCTURAL_HEADER_RE.match(line)
    if m_struct:
        keyword = m_struct.group(1)
        for k, lvl, key in HIERARCHY_TOKENS:
            if k == keyword:
                remainder = line[len(keyword):].strip()
                return keyword, lvl, key, remainder

    # 2. Check for Articles (المادة / مادة)
    if line.startswith('المادة') or line.startswith('املادة') or line.startswith('مادة'):
        if line.startswith('مادة') and not line.startswith('المادة'):
            keyword = 'مادة'
        elif line.startswith('املادة'):
            keyword = 'املادة'
        else:
            keyword = 'المادة'
        remainder = line[len(keyword):].strip()

        # Verify remainder looks like a number or ordinal
        if remainder and (remainder[0].isdigit() or remainder.startswith(('الأول', 'الثاني'))):
            return keyword, 0, 'article', remainder

    # 3. Fallback for simple tokens
    for token, level, key in HIERARCHY_TOKENS:
        if line == token:
            return token, level, key, ""

    return None, -1, None, None


# ─────────────────────────────────────────────────────────────
# PDF extraction using LiteParse
# ─────────────────────────────────────────────────────────────
def extract_text_liteparse(pdf_path: str | bytes, first_page: int = 1,
                           last_page: int | None = None, use_ocr: bool = False,
                           verbose: bool = False) -> str:
    """
    Extract text from PDF using LiteParse with spatial layout preservation.

    Args:
        pdf_path: Path to the PDF file or bytes content
        first_page: First page to parse (1-indexed)
        last_page: Last page to parse (None for end)
        use_ocr: Enable OCR for scanned documents
        verbose: Print progress information

    Returns:
        Raw text with page separators for downstream processing
    """
    try:
        # Configure parser
        parser = LiteParse(
            ocr_enabled=use_ocr,
            ocr_language='ara',  # Arabic language for OCR
            dpi=300,
        )

        if verbose:
            print(
                f"🔍  Parsing with LiteParse: {pdf_path if isinstance(pdf_path, str) else '<bytes>'}")
            if use_ocr:
                print("📷  OCR enabled for scanned document support")

        # Parse the document (handles both path and bytes)
        result = parser.parse(pdf_path)

        # Build text output with page markers (form-feed style for compatibility)
        output_lines = []

        for page in result.pages:
            # Skip pages outside requested range
            if page.page_num < first_page:
                continue
            if last_page and page.page_num > last_page:
                break

            # Add page separator (form-feed character for pdftotext compatibility)
            if page.page_num > first_page:
                output_lines.append('\f')

            # Extract text items sorted by vertical position (top-to-bottom reading order)
            # LiteParse returns text_items with spatial info; we sort by y-coordinate
            text_items = sorted(
                page.text_items,
                key=lambda item: (item.bbox.y if hasattr(item, 'bbox') else 0,
                                  item.bbox.x if hasattr(item, 'bbox') else 0)
            )

            for item in text_items:
                text = item.text.strip() if hasattr(item, 'text') else str(item).strip()
                if text:  # Skip empty items
                    output_lines.append(text)

        if verbose:
            total_lines = len(output_lines)
            print(
                f"📝  Extracted ~{total_lines:,} lines from {len(result.pages)} pages")

        return '\n'.join(output_lines)
    
    except Exception as e:
        error_msg = str(e)
        if "OCR" in error_msg and not use_ocr:
            print(f"⚠️  Warning: Document may contain scanned images.")
            print(f"    Try re-running with --ocr flag to enable OCR.")
        raise RuntimeError(
           f"LiteParse extraction failed:\n{error_msg}"
             )