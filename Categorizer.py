import json
import meilisearch

# Connect directly to your running Docker container
client = meilisearch.Client("http://localhost:7800")

def classify_law(title: str, text: str) -> str:
    """ Automatically categorizes Moroccan laws into 4 main buckets using bilingual keyword matching """
    title_lower = title.lower()
    text_lower = text.lower()
    
    # 1. Constitutional Law
    if any(x in title_lower or x in text_lower for x in ["constitution", "constitutionnel", "الدستور", "دستوري"]):
        return "Constitutional"
        
    # 2. Criminal Law / Droit Pénal
    elif any(x in title_lower or x in text_lower for x in ["pénal", "général", "infraction", "crime", "جنائي", "العقوبات", "زجري"]):
        return "Criminal"
        
    # 3. Business & Commercial Law / Droit des Affaires
    elif any(x in title_lower or x in text_lower for x in ["commerce", "commercial", "sociétés", "fiscal", "impôt", "تجاري", "الشركات", "التجارة"]):
        return "Business"
        
    # 4. Civil Law / Droit Civil (DOC)
    elif any(x in title_lower or x in text_lower for x in ["civil", "obligations", "contrats", "مدني", "التزامات", "عقود"]):
        return "Civil"
        
    else:
        return "Uncategorized"

def index_ramis_production_data(filepath: str):
    """ Reads Rami's new production flat JSON format and indexes it into Meilisearch """
    with open(filepath, 'r', encoding='utf-8') as f:
        articles_list = json.load(f)
        
    if not articles_list:
        print(f"Skipping empty file: {filepath}")
        return

    # Grab the document title from the first item for logging
    sample_title = articles_list[0].get("doc_title", "Unknown Document")
    print(f"Processing production file: {sample_title}...")
    
    flattened_articles = []
    
    for art in articles_list:
        # Use the specific fields provided in the new output structure
        title = art.get("doc_title", "Unknown")
        text = art.get("text", "")
        
        group = classify_law(title, text)
        
        flattened_articles.append({
            "id": art.get("id"),  # Using Rami's pre-calculated unique ID directly
            "group": group,
            "document_title": title,
            "language": art.get("doc_language", "ar"),
            "type": art.get("doc_type", "Dahir"),
            "doc_source_file": art.get("doc_source_file"),
            "article_number": art.get("article_num"),
            "sort_key": art.get("sort_key"),
            "path": art.get("path"),
            "slug": art.get("slug"),
            "chapter": art.get("breadcrumb_chapter", ""),
            "text": text
        })

    # Target or create the search index inside the container
    index = client.index("moroccan_laws")
    
    # Enable filtering settings so your checkboxes work later on your frontend UI
    index.update_filterable_attributes(["group", "language", "type"])
    
    # Fire the payload into Docker and block until processing finishes
    task = index.add_documents(flattened_articles)
    client.wait_for_task(task.task_uid)
    print(f"🎉 Success! Uploaded {len(flattened_articles)} production articles to your Docker index.")

if __name__ == "__main__":
    index = client.index("moroccan_laws")
    # 🧹 Wipes out every document inside this index
    task = index.delete_all_documents()
    client.wait_for_task(task.task_uid)

    print("🗑️ All documents successfully deleted from the index!")

    try:
        print("🚀 Starting production data pipeline injection...")
        # Make sure your test_law.json contains the new array snippet you just pasted!
        index_ramis_production_data(filepath="test_law.json")
    except Exception as e:
        print(f"❌ Error during injection: {e}")