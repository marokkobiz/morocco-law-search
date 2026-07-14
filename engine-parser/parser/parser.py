from parser.extract import (
    clean,
    is_noise,
    classify_line,
    parse_article_number,
    ordinal_sort_key,
)

from parser.models import Document, Article, Context, Metadata
from parser.structure import (
    build_path,
    build_slug,
    hierarchy_depth,
)
import re

class BreadcrumbTracker:
    """Tracks the current position in the document hierarchy."""

    def __init__(self):
        # level → (json_key, label)
        self._crumbs: dict[int, tuple[str, str]] = {}

    def update(self, level: int, json_key: str, label: str):
        """Set the breadcrumb for `level` and clear all lower structural levels."""
        self._crumbs[level] = (json_key, label)
        # Clear everything at strictly lower levels (children)
        keys_to_delete = [l for l in self._crumbs.keys() if l < level]
        for l in keys_to_delete:
            del self._crumbs[l]

    def snapshot(self) -> dict[str, str]:
        """Return current breadcrumb dict (key → label), excluding the article level."""
        result = {}
        # Sort by level descending so Book comes first
        for level in sorted(self._crumbs.keys(), reverse=True):
            json_key, label = self._crumbs[level]
            if level > 0:   # skip the article itself
                result[json_key] = label
        return result




# ─────────────────────────────────────────────────────────────
# Core parser
# ─────────────────────────────────────────────────────────────
def parse(raw_text: str, verbose: bool = False) -> list[dict]:
    """
    Walk through the raw text and build a list of article dicts,
    each carrying a breadcrumb snapshot of the surrounding hierarchy.
    """
    articles: dict[int, dict] = {}
    breadcrumb = BreadcrumbTracker()

    current_key:   int | None = None
    current_label: str | None = None
    current_body:  list[str] = []
    current_page:  int = 1
    current_crumb: dict = {}

    # State machine for headers that span two lines
    pending_header: tuple | None = None

    def flush_article():
        nonlocal current_key, current_label, current_body, current_crumb
        if current_key is None:
            return

        text = '\n'.join(l for l in current_body if l).strip()
        text = re.sub(r'\n{3,}', '\n\n', text)

        if not text:
            if verbose:
                print(f"  ⚠️ Empty article {current_label}")
            return

        if current_key in articles:
            # Append if duplicate key found
            articles[current_key]['text'] += '\n' + text
        else:
            article = {
               "article": current_label,
               "_sort_key": current_key,
               "page": current_page,
               "breadcrumb": current_crumb,
               "text": text,
                  }

            article["path"] = build_path(article)
            article["slug"] = build_slug(article)
            article["depth"] = hierarchy_depth(article)

            articles[current_key] = article
            if verbose:
                bc_str = ' > '.join(
                    articles[current_key]['breadcrumb'].values())
                print(
                    f'  ✅ المادة {current_label:>6} (p.{current_page}) [{bc_str}]')

    lines = raw_text.splitlines()
    for i, raw_line in enumerate(lines):
        # Track pages via form-feed character
        if '\f' in raw_line:
            current_page += raw_line.count('\f')
            raw_line = raw_line.replace('\f', '')

        line = clean(raw_line)

        # Skip noise
        if is_noise(line):
            continue

        kw, level, json_key, remainder = classify_line(line)

        if kw is None:
            # --- Body Text ---
            if pending_header is not None:
                # The previous line was a header keyword, this line is its Title
                ph_level, ph_key, ph_base_label = pending_header
                full_label = f'{ph_base_label} : {line}'
                breadcrumb.update(ph_level, ph_key, full_label)
                pending_header = None
            elif current_key is not None:
                # Add to current article body
                current_body.append(line)
            continue

        # --- Structural Element Found ---

        if level == 0:
            # It's an Article (المادة)
            sort_key, label = parse_article_number(remainder)

            if sort_key is None:
                # Failed to parse number - might be ordinal
                ord_key = ordinal_sort_key(remainder)
                if ord_key != 99:
                    sort_key = ord_key
                    label = remainder
                else:
                    # Not an article number, treat as body text if inside an article
                    if current_key is not None:
                        current_body.append(line)
                    continue

            # Flush previous article
            flush_article()

            # Start new article
            current_key = sort_key
            current_label = label
            current_body = []
            current_crumb = breadcrumb.snapshot()
            pending_header = None

        else:
            # It's a Header (Book, Chapter, etc.)
            flush_article()
            current_key = None
            current_body = []

            base_label = f'{kw} {remainder}'.strip()

            # Check if the title is on the same line
            if ':' in remainder or ' - ' in remainder or len(remainder.split()) > 2:
                # Likely inline title
                breadcrumb.update(level, json_key, base_label)
                pending_header = None
            else:
                # Title likely on next line
                pending_header = (level, json_key, base_label)

    # Flush last article
    flush_article()

    return sorted(articles.values(), key=lambda a: a['_sort_key'])
