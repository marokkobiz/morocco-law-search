import json
import os
import uuid
from pathlib import Path

def build_document_output(articles: list[dict], title: str, language: str = "ar", law_type: str = "unknown", source_file: str = ""):
    """Convert parser article output into MarocLoi v1 JSON format."""
    return {
        "document": {
            "title": title,
            "language": language,
            "type": law_type,
            "source_file": source_file
        },
        "articles": [
            {
                "article": article["article"],
                "_sort_key": article["_sort_key"],
                "page": article.get("page", 1),
                "breadcrumb": article.get("breadcrumb", {}),
                "text": article["text"],
                "path": article["path"],
                "slug": article["slug"],
                "depth": article.get("depth", 0)
            }
            for article in articles
        ]
    }

def save_document_json(data: dict, output_path: str):
    """Save final parser output."""
    path = Path(output_path)
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

def save_meilisearch_json(extracted_articles: list, doc_metadata: dict, output_filepath: str):
    """Denormalizes the data for flat Meilisearch ingestion."""
    meilisearch_payload = []
    
    for article in extracted_articles:
        safe_id = f"doc_{uuid.uuid4().hex[:8]}"
        flat_record = {
            "doc_title": doc_metadata.get("title", ""),
            "doc_language": doc_metadata.get("language", "ar"),
            "doc_type": doc_metadata.get("type", "Law"),
            "doc_source_file": doc_metadata.get("source_file", ""),
            "id": safe_id,
            "article_num": article.get("article", ""),
            "sort_key": article.get("_sort_key", 0),
            "text": article.get("text", ""),
            "path": article.get("path", ""),
            "slug": article.get("slug", ""),
            "breadcrumb_chapter": article.get("breadcrumb", {}).get("chapter", ""),
        }
        meilisearch_payload.append(flat_record)

    os.makedirs(os.path.dirname(output_filepath), exist_ok=True)
    with open(output_filepath, 'w', encoding='utf-8') as f:
        json.dump(meilisearch_payload, f, ensure_ascii=False, indent=2)
        
    return len(meilisearch_payload)