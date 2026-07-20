"""
Arabic Legal Text Normalizer

Character‑level standardization for Moroccan legal Arabic text.
- Unicode normalization
- Control character removal
- Alef unification (configurable)
- Tatweel / Kashida removal
- Diacritic removal (with optional preservation of shadda)
- Whitespace and punctuation normalization
- Arabic‑Indic digit unification (optional)

Can be used standalone or after the LegalTextCleaner.
"""

import re
import unicodedata
from typing import Optional, Dict, Any


DIGIT_TRANSLATION = str.maketrans(
    "٠١٢٣٤٥٦٧٨٩",
    "0123456789"
)

class ArabicTextNormalizer:
    """Standardizes Arabic text at the character level."""

    def __init__(self, config: Optional[Dict[str, Any]] = None):
        cfg = config or {}

        # Which normalizations to apply
        self.unify_alef = cfg.get("unify_alef", True)
        self.remove_tatweel = cfg.get("remove_tatweel", True)
        self.remove_diacritics = cfg.get("remove_diacritics", True)
        self.preserve_shadda = cfg.get("preserve_shadda", False)
        # Arabic‑Indic → ASCII
        self.unify_digits = cfg.get("unify_digits", True)
        self.normalize_spaces = cfg.get("normalize_spaces", True)
        self.fix_punctuation_spacing = cfg.get("fix_punctuation_spacing", True)
        self.remove_control_chars = cfg.get("remove_control_chars", True)
        self.normalization_form = cfg.get("normalization_form", "NFKC")

    def normalize(self, text: str) -> str:
        """Apply all configured normalizations to a text string."""
        if not text:
            return ""

        # 1. Unicode normalization (NFKC by default)
        text = unicodedata.normalize(self.normalization_form, text)

        # 2. Remove control characters (bidi marks, zero‑width spaces, etc.)
        if self.remove_control_chars:
            text = re.sub(
                r'[\u200b-\u200f\u202a-\u202e\u2060-\u2069\uFEFF]+', '', text)

        # 3. Alef unification (أ, إ, آ → ا)
        if self.unify_alef:
            # Alef with hamza above/below, Alef with madda
            text = re.sub(r'[أإآ]', 'ا', text)

        # 4. Remove tatweel (kashida)
        if self.remove_tatweel:
            text = re.sub(r'ـ+', '', text)

        # 5. Diacritic removal (optionally preserve shadda)
        if self.remove_diacritics:
            if self.preserve_shadda:
                # Remove all diacritics except shadda (ّ)
                text = re.sub(
                    r'[\u064B-\u064E\u0650-\u0652\u0654-\u065F\u0670]', '', text)
            else:
                # Remove all Arabic diacritics
                text = re.sub(r'[\u064B-\u065F\u0670]', '', text)

        # 6. Digit unification (Arabic‑Indic → ASCII)
        if self.unify_digits:
            # Map Arabic-Indic digits (٠١٢٣٤٥٦٧٨٩) to ASCII (0-9)
            text = text.translate(DIGIT_TRANSLATION)

        # 7. Whitespace normalization
        if self.normalize_spaces:
            # Replace tabs, non-breaking spaces, etc. with normal space
            text = re.sub(
                r'[\t\u00A0\u1680\u180E\u2000-\u200A\u202F\u205F\u3000]+', ' ', text)
            # Collapse multiple spaces
            text = re.sub(r' +', ' ', text)
            # Trim each line
            text = '\n'.join(line.strip() for line in text.split('\n'))
            # Collapse multiple newlines (max 2)
            text = re.sub(r'\n{3,}', '\n\n', text)

        # 8. Punctuation spacing (Arabic rules)
        if self.fix_punctuation_spacing:
            # Arabic punctuation should have no preceding space
            text = re.sub(r'\s+([،؛:])', r'\1', text)
            # Opening brackets/parentheses: space before, no space after
            text = re.sub(r'\s+([\(\[])\s*', r' \1', text)
            # Closing brackets: no space before, space after
            text = re.sub(r'\s*([\)\]])\s+', r'\1 ', text)
            # French quotation marks
            text = re.sub(r'\s*«\s+', ' « ', text)
            text = re.sub(r'\s+»\s*', ' » ', text)
            # Clean up double spaces that may have been introduced
            text = re.sub(r' +', ' ', text)

        # Final trim
        return text.strip()

    def normalize_lines(self, lines: list[str]) -> list[str]:
        """Normalize a list of text lines."""
        return [self.normalize(line) for line in lines]
