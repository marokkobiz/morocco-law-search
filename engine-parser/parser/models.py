from dataclasses import dataclass, field
from typing import List, Optional


@dataclass
class Metadata:
    language: str = "ar"
    extraction_method: str = "pymupdf"
    source_file: str = ""
    page_count: int = 0


@dataclass
class Context:
    book: Optional[str] = None
    title: Optional[str] = None
    chapter: Optional[str] = None
    section: Optional[str] = None


@dataclass
class Article:
    article_number: str
    text: str
    context: Context = field(default_factory=Context)
    footnotes: List[str] = field(default_factory=list)


@dataclass
class Document:
    law_name: str
    metadata: Metadata
    articles: List[Article] = field(default_factory=list)