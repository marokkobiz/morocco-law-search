"""Structure Parser for Moroccan Legal Texts.

Extracts hierarchical structure (books, sections, chapters, articles)
from normalized Arabic legal text.

Falls back to heading-level chunking when no individual articles are found.
"""

import re
from typing import List, Dict, Optional
import json


def _extract_heading_number(heading_text: str) -> str:
    """Extract a numeric or ordinal identifier from a heading like 'الباب الاول' or 'الفصل 18'."""
    text = heading_text.strip()
    # Try Arabic ordinals (الاول → 1, الثاني → 2, etc.)
    arabic_ordinals = {
        'الاول': '1', 'الأول': '1', 'اول': '1', 'أول': '1',
        'الثاني': '2', 'الثانى': '2', 'ثاني': '2', 'ثانى': '2',
        'الثالث': '3', 'ثالث': '3',
        'الرابع': '4', 'رابع': '4',
        'الخامس': '5', 'خامس': '5',
        'السادس': '6', 'سادس': '6',
        'السابع': '7', 'سابع': '7',
        'الثامن': '8', 'ثامن': '8',
        'التاسع': '9', 'تاسع': '9',
        'العاشر': '10', 'عاشر': '10',
    }
    # Try to find a digit first
    digit_match = re.search(r'(\d+)', text)
    if digit_match:
        return digit_match.group(1)
    # Try Arabic ordinal words
    for word, num in arabic_ordinals.items():
        if word in text:
            return num
    # Fallback: hash the text for a short identifier
    return str(hash(text) % 10000)

def build_path(article: dict) -> str:
    """
    Build a readable hierarchy path.

    Example:
    الكتاب الأول > الباب الثاني > الفصل الأول > المادة 147
    """

    parts = []

    breadcrumb = article.get("breadcrumb", {})

    for value in breadcrumb.values():
        if value:
            parts.append(value)

    if article.get("article"):
        parts.append(f"المادة {article['article']}")

    return " > ".join(parts)


def hierarchy_depth(article: dict) -> int:
    """
    Number of hierarchy levels.
    """

    return len(article.get("breadcrumb", {}))


def build_slug(article: dict) -> str:
    """
    Stable slug usable later by Laravel / Qdrant.
    """

    pieces = []

    breadcrumb = article.get("breadcrumb", {})

    for value in breadcrumb.values():
        slug = (
            value.replace(" ", "-")
                 .replace("/", "-")
                 .replace(":", "")
        ).lower()

        pieces.append(slug)

    if article.get("article"):
        pieces.append(f"article-{article['article']}")

    return "/".join(pieces)