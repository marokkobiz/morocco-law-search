import json
import meilisearch

# Connect directly to your running Docker container
client = meilisearch.Client("http://localhost:7700")

def classify_law(title: str, first_article_text: str) -> str:
    """ Automatically categorizes Moroccan laws into 4 main buckets using keyword matching """
    title_lower = title.lower()
    text_lower = first_article_text.lower()
    
    if "constitution" in title_lower or "الدستور" in title_lower:
        return "Constitutional"
    elif any(x in title_lower or x in text_lower for x in ["pénal", "général", "جنائي", "العقوبات", "زجري"]):
        return "Criminal"
    elif any(x in title_lower or x in text_lower for x in ["commerce", "sociétés", "fiscal", "تجاري", "الشركات", "التجارة"]):
        return "Business"
    else:
        return "Civil"

def index_ramis_data(filepath: str, doc_title: str, doc_language: str = "ar", doc_type: str = "Dahir"):
    """ Reads Rami's new flat JSON output, flattens it for search, and indexes it """
    with open(filepath, 'r', encoding='utf-8') as f:
        articles_list = json.load(f)
        
    if not articles_list:
        print(f"Skipping empty file: {filepath}")
        return

    # Determine classification group using the first article text chunk
    group = classify_law(doc_title, articles_list[0]["text"])
    print(f"Processing [{group}] -> {doc_title}...")
    
    flattened_articles = []
    
    for art in articles_list:
        # Build a safe, unique ID key string for Meilisearch row-tracking
        clean_id = f"doc_{doc_title}_art_{art['article']}"
        clean_id = "".join(c for c in clean_id if c.isalnum() or c in ("_", "-"))
        
        flattened_articles.append({
            "id": clean_id,
            "group": group,
            "document_title": doc_title,
            "language": doc_language,
            "type": doc_type,
            "article_number": art["article"],
            "page_number": art.get("page"),
            "full_path": art.get("path"),
            "chapter": art["breadcrumb"].get("chapter"),
            "section": art["breadcrumb"].get("section"),
            "text": art["text"]
        })

    # target or create the search index inside the container
    index = client.index("moroccan_laws")
    
    # Enable filtering settings so your checkboxes work later on your frontend UI
    index.update_filterable_attributes(["group", "language", "type"])
    
    # Fire the payload into Docker and block until processing finishes
    task = index.add_documents(flattened_articles)
    client.wait_for_task(task.task_uid)
    print(f"🎉 Success! Uploaded {len(flattened_articles)} articles to your Docker index.")

if __name__ == "__main__":
    # Once Rami hands you his first JSON file, drop it in your folder and test it here!
    print("Pipeline ready. Pass a JSON file path to index_ramis_data() to populate Docker.")