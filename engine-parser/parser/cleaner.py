"""
Arabic Legal Text Cleaner

Removes structural noise from extracted PDF text before normalization:
- Character duplication (e.g., ممددووننةة → مدونة)
- Page numbers (standalone digits, - XX - patterns)
- Table of contents (dotted leaders + reversed Arabic)
- Repeated headers/footers across pages
- Broken mid-sentence line breaks
- Duplicate paragraphs
- Watermarks and scanning artifacts
- Non-text elements

Input: Raw extracted text
Output: Cleaned text
"""

import re
import logging
from typing import List, Optional, Dict, Any
from difflib import SequenceMatcher


# ----------------------------------------------------------------------
# Configuration
# ----------------------------------------------------------------------

# Patterns for noise detection
WATERMARK_PATTERNS = [
    r"نسخة (?:غير|غير)? ?قابلة للبيع",
    r"ملكية (?:عامة|خاصة)",
    r"جميع الحقوق محفوظة",
    r"©.*\d{4}",
    r"www\..+\.[a-z]{2,}",
    r"https?://\S+",
]

HEADER_FOOTER_PATTERNS = [
    r"الجريدة الرسمية\s*(?:عدد|نمرة)?\s*\d*",
    r"المملكة المغربية",
    r"ظهير شريف",
    r"صفحة\s*\d+\s*من\s*\d+",
    r"العدد\s*\d+",
]

# Patterns for page numbers
PAGE_NUMBER_PATTERNS = [
    r"^\s*-\s*\d{1,4}\s*-\s*$",   # - 22 -
    r"^\s*\d{1,4}\s*$",           # standalone number
]

# Minimum characters for a meaningful text block
MIN_BLOCK_LENGTH = 3

# Similarity threshold for duplicate detection (0.0 to 1.0)
DUPLICATE_SIMILARITY = 0.85

# Table of contents detection
# dotted leaders + page number
TOC_LINE_PATTERN = re.compile(r'\.{3,}\s*\d+\s*$')


# ----------------------------------------------------------------------
# Cleaner Class
# ----------------------------------------------------------------------

class LegalTextCleaner:
    """Cleans Arabic legal text extracted from PDFs."""

    def __init__(self, config: Optional[Dict[str, Any]] = None):
        self.config = config or {}
        self.logger = logging.getLogger("Cleaner")
        if not self.logger.handlers:
            handler = logging.StreamHandler()
            handler.setFormatter(logging.Formatter(
                "%(asctime)s | %(levelname)-8s | %(message)s"))
            self.logger.addHandler(handler)

        # Compile patterns
        self.watermark_re = [re.compile(p, re.IGNORECASE)
                             for p in WATERMARK_PATTERNS]
        self.header_footer_re = [re.compile(
            p, re.IGNORECASE) for p in HEADER_FOOTER_PATTERNS]
        self.page_number_re = [re.compile(p) for p in PAGE_NUMBER_PATTERNS]

    # ------------------------------------------------------------------
    # New: character duplication fix
    # ------------------------------------------------------------------
    @staticmethod
    def fix_character_duplication(text: str) -> str:
        """
        Fix PDF extraction artifacts where each character appears 2-3 times.
        Example: ممددووننةة → مدونة
        Only fixes Arabic script characters.
        """
        if not text:
            return text

        # This regex finds an Arabic character followed by 1-2 repetitions of itself
        # and replaces with a single character.
        # It handles both single and double duplicate sequences.
        def dedup(match):
            return match.group(1)

        # Pattern: an Arabic letter, then 1 or 2 identical copies (non-greedy)
        pattern = re.compile(r'([\u0600-\u06FF])\1{1,2}')
        fixed = pattern.sub(dedup, text)
        return fixed

    # ------------------------------------------------------------------
    # Page number removal (enhanced)
    # ------------------------------------------------------------------
    def remove_page_numbers(self, text: str) -> str:
        """Remove standalone page numbers and - XX - patterns."""
        lines = text.split("\n")
        cleaned = []
        for line in lines:
            stripped = line.strip()
            is_page_no = False
            for pattern in self.page_number_re:
                if pattern.match(stripped):
                    is_page_no = True
                    break
            if not is_page_no:
                cleaned.append(line)
            else:
                self.logger.debug(f"Removed page number: {stripped}")
        return "\n".join(cleaned)

    # ------------------------------------------------------------------
    # TOC detection and removal
    # ------------------------------------------------------------------
    def remove_table_of_contents(self, text: str) -> str:
        """
        Detect and remove the table of contents.
        TOC is identified by lines containing dotted leaders (......) and page numbers,
        often with reversed Arabic text.
        """
        lines = text.split("\n")
        # Find blocks of consecutive TOC-like lines
        toc_indices = set()
        in_toc_block = False
        toc_start = 0
        for i, line in enumerate(lines):
            stripped = line.strip()
            if TOC_LINE_PATTERN.search(stripped):
                if not in_toc_block:
                    toc_start = i
                    in_toc_block = True
            else:
                if in_toc_block:
                    # End of TOC block: mark all lines from start to here
                    # Only remove if block is substantial (>5 lines)
                    if i - toc_start > 5:
                        for j in range(toc_start, i):
                            toc_indices.add(j)
                    in_toc_block = False

        # Handle TOC at end of document
        if in_toc_block and (len(lines) - toc_start) > 5:
            for j in range(toc_start, len(lines)):
                toc_indices.add(j)

        cleaned = [line for i, line in enumerate(
            lines) if i not in toc_indices]
        if toc_indices:
            self.logger.info(f"Removed TOC ({len(toc_indices)} lines)")
        return "\n".join(cleaned)

    # ------------------------------------------------------------------
    # Repetition-based header/footer removal (multi-page)
    # ------------------------------------------------------------------
    def remove_repeating_headers(self, pages: List[str], threshold: float = 0.7) -> List[str]:
        """
        Remove lines that appear on more than `threshold` of pages.
        This catches running headers like 'مدونة التجارة' without needing bbox data.
        """
        if len(pages) < 3:
            return pages

        # Count line occurrences across pages
        line_counts: Dict[str, int] = {}
        for page_text in pages:
            seen = set()
            for line in page_text.split("\n"):
                stripped = line.strip()
                if stripped and len(stripped) > 5:
                    if stripped not in seen:
                        line_counts[stripped] = line_counts.get(
                            stripped, 0) + 1
                        seen.add(stripped)

        # Identify lines that appear on >threshold of pages
        min_pages = max(2, int(len(pages) * threshold))
        noise_lines = {line for line,
                       count in line_counts.items() if count >= min_pages}

        if noise_lines:
            self.logger.info(
                f"Identified {len(noise_lines)} repeating header/footer lines")

        # Remove those lines from each page
        cleaned_pages = []
        for page_text in pages:
            lines = page_text.split("\n")
            kept = [line for line in lines if line.strip() not in noise_lines]
            cleaned_pages.append("\n".join(kept))

        return cleaned_pages

    # ------------------------------------------------------------------
    # New: fix Arabic lam-alef ligature broken by PDF extraction
    # ------------------------------------------------------------------
    @staticmethod
    def fix_lam_alef_ligature(text: str) -> str:
        """
        Fix PDF extraction bug where lam-alef ligature (لا) is split as 'ا + م'
        instead of 'ل + ا', producing 'امل...' instead of 'الم...'.

        Example: املادة → المادة, املغرب → المغرب, املركبة → المركبة
        """
        if not text:
            return text
        # Replace 'امل' at word start with 'الم' when followed by an Arabic letter
        # that can appear after the definite article ال
        arabic_after_al = r'[ؤئأإاويىهةتدذرزسشصضطظعغفقكلمنه]'
        text = re.sub(r'\bامل(?=' + arabic_after_al + r')', 'الم', text)
        return text

    # ------------------------------------------------------------------
    # Individual cleaning operations (from original cleaner)
    # ------------------------------------------------------------------
    def remove_header_footer_patterns(self, text: str) -> str:
        """Remove lines matching known header/footer patterns."""
        lines = text.split("\n")
        cleaned = []
        for line in lines:
            stripped = line.strip()
            is_noise = any(p.search(stripped) for p in self.header_footer_re)
            if not is_noise:
                cleaned.append(line)
        return "\n".join(cleaned)

    def remove_watermarks(self, text: str) -> str:
        """Remove watermark text patterns."""
        for pattern in self.watermark_re:
            text = pattern.sub("", text)
        return text

    def fix_line_breaks(self, text: str) -> str:
        """Merge broken mid-sentence lines while preserving paragraph breaks."""
        lines = text.split("\n")
        if len(lines) < 2:
            return text

        merged = []
        buffer = ""
        for line in lines:
            stripped = line.strip()
            if not stripped:
                if buffer:
                    merged.append(buffer.strip())
                    buffer = ""
                merged.append("")
                continue

            if buffer:
                # Merge if previous line doesn't end with sentence terminator
                # and current line doesn't start with article marker
                ends_abruptly = not re.search(r"[.؟!؛,:]\s*$", buffer)
                starts_continuation = not re.match(
                    r"^(?:المادة|الفصل|الباب|القسم|الكتاب|مادة|فصل|باب|قسم|كتاب)\s",
                    stripped
                )
                if ends_abruptly and starts_continuation and len(buffer.split()) > 2:
                    buffer += " " + stripped
                else:
                    merged.append(buffer.strip())
                    buffer = stripped
            else:
                buffer = stripped

        if buffer:
            merged.append(buffer.strip())

        # Collapse multiple blank lines into one
        result = []
        prev_blank = False
        for line in merged:
            if not line:
                if not prev_blank:
                    result.append(line)
                prev_blank = True
            else:
                result.append(line)
                prev_blank = False
        return "\n".join(result)

    def remove_blank_lines(self, text: str) -> str:
        """Collapse multiple consecutive blank lines into one."""
        lines = text.split("\n")
        result = []
        prev_empty = False
        for line in lines:
            is_empty = not line.strip()
            if is_empty and prev_empty:
                continue
            result.append(line)
            prev_empty = is_empty
        return "\n".join(result)

    def remove_short_blocks(self, text: str) -> str:
        """Remove blocks shorter than MIN_BLOCK_LENGTH."""
        lines = text.split("\n")
        return "\n".join(
            line for line in lines
            if not line.strip() or len(line.strip()) >= MIN_BLOCK_LENGTH
        )

    def remove_duplicate_paragraphs(self, text: str) -> str:
        """Remove near-duplicate paragraphs (fuzzy matching)."""
        paragraphs = re.split(r"\n\s*\n", text)
        if len(paragraphs) < 2:
            return text
        unique = []
        for para in paragraphs:
            stripped = para.strip()
            if not stripped:
                unique.append(para)
                continue
            is_dup = any(
                SequenceMatcher(None, stripped, e.strip()
                                ).ratio() >= DUPLICATE_SIMILARITY
                for e in unique if e.strip()
            )
            if not is_dup:
                unique.append(para)
        return "\n\n".join(unique)

    def remove_repeated_phrases(self, text: str) -> str:
        """Remove identical consecutive lines."""
        lines = text.split("\n")
        if len(lines) < 2:
            return text
        cleaned = [lines[0]]
        for i in range(1, len(lines)):
            if lines[i].strip() and lines[i].strip() == lines[i - 1].strip():
                continue
            cleaned.append(lines[i])
        return "\n".join(cleaned)

    def normalize_whitespace(self, text: str) -> str:
        """Normalize spaces, tabs, and whitespace."""
        text = text.replace("\t", " ")
        text = "\n".join(line.rstrip() for line in text.split("\n"))
        text = re.sub(r"[^\S\n]+", " ", text)
        text = re.sub(r"^[^\S\n]+", "", text, flags=re.MULTILINE)
        return text.strip()

    def remove_non_text_elements(self, text: str) -> str:
        """Remove lines that are mostly symbols."""
        lines = text.split("\n")
        cleaned = []
        for line in lines:
            stripped = line.strip()
            if not stripped:
                cleaned.append(line)
                continue
            alpha = sum(1 for c in stripped if c.isalpha())
            if len(stripped) > 0 and alpha / len(stripped) < 0.3:
                continue
            if re.fullmatch(r"[_\-\*\.\s\|\\/]+", stripped):
                continue
            cleaned.append(line)
        return "\n".join(cleaned)

    # ------------------------------------------------------------------
    def clean_text(self, text: str) -> str:
        """Clean raw text without structured input."""
        if not text:
            return ""
        text = self.fix_character_duplication(text)
        text = self.fix_lam_alef_ligature(text)
        text = self.remove_watermarks(text)
        text = self.remove_header_footer_patterns(text)
        text = self.remove_page_numbers(text)
        text = self.remove_table_of_contents(text)
        text = self.fix_line_breaks(text)
        text = self.remove_blank_lines(text)
        text = self.remove_short_blocks(text)
        text = self.remove_duplicate_paragraphs(text)
        text = self.remove_repeated_phrases(text)
        text = self.remove_non_text_elements(text)
        text = self.normalize_whitespace(text)
        return text
