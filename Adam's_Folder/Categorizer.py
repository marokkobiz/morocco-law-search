import os
import json
import meilisearch

# Connect directly to your running Docker container
client = meilisearch.Client("http://localhost:7800")

# The output folder from your Chunker.py script
JSON_INPUT_DIR = "json_laws"

def classify_law(title: str, text: str) -> str:
    """
    Automatically categorizes Moroccan laws into 4 main buckets using 
    an expanded, high-accuracy bilingual keyword matching schema.
    """
    title_lower = title.lower()
    text_lower = text.lower()
    
    # 1. Constitutional Law (Droit Constitutionnel / التنظيم الدستوري والاداري)
    constitutional_keywords = [
        # French
        "constitution", "constitutionnel", "loi organique", "parlement", 
        "chambre des représentants", "chambre des conseillers", "cour constitutionnelle", 
        "référendum", "souveraineté", "prérogatives", "pouvoir législatif",
        # Arabic
        "الدستور", "دستوري", "قانون تنظيمي", "البرلمان", "مجلس النواب", 
        "مجلس المستشارين", "المحكمة الدستورية", "استفتاء", "السيادة", "التشريعي"
    ]
    
    # 2. Criminal Law / Droit Pénal & Procédure Pénale (القانون الجنائي والمسطرة الجنائية)
    criminal_keywords = [
        # French
        "pénal", "général", "infraction", "crime", "délit", "contravention", 
        "amende", "emprisonnement", "réclusion", "détention", "procédure pénale", 
        "poursuite", "inculpé", "accusé", "complice", "vol", "homicide", "saisie",
        # Arabic
        "جنائي", "العقوبات", "زجري", "الجنايات", "الجنح", "المخالفات", "غرامة", 
        "سجن", "حبس", "اعتقال", "متابعة", "متهم", "شريك", "المسطرة الجنائية", 
        "النيابة العامة", "وكيل الملك", "تلبس", "جريمة"
    ]
    
    # 3. Business & Commercial Law / Droit des Affaires, Fiscal & Douanes (القانون التجاري والمالي)
    business_keywords = [
        # French
        "commerce", "commercial", "sociétés", "fiscal", "impôt", "douane", 
        "concurrence", "faillite", "redressement", "liquidation", "sarl", "sa ",
        "registre du commerce", "chèque", "traite", "effets de commerce", "lettre de change",
        "propriété industrielle", "brevet", "marché public", "consommation", "investissement",
        # Arabic
        "تجاري", "الشركات", "التجارة", "ضريبي", "الضرائب", "الجمارك", "المنافسة", 
        "إفلاس", "صعوبات المقاولة", "التصفية القضائية", "السجل التجاري", "شيك", 
        "كمبيالة", "سند لأمر", "الملكية الصناعية", "براءة اختراع", "صفقات عمومية", 
        "الاستثمار", "المقاولة"
    ]
    
    # 4. Civil Law / Droit Civil & D.O.C (قانون الالتزامات والعقود / الأحوال الشخصية)
    civil_keywords = [
        # French
        "civil", "obligations", "contrats", "d.o.c", "responsabilité civile", 
        "bail", "location", "vente", "hypothèque", "propriété foncière", "donation", 
        "héritage", "succession", "statut personnel", "famille", "moudawana", 
        "tutelle", "prescription", "préjudice", "indemnisation",
        # Arabic
        "مدني", "التزامات", "عقود", "الالتزامات والعقود", "المسؤولية المدنية", 
        "كراء", "بيع", "رهن", "تحفيظ عقاري", "هبة", "إرث", "التركات", 
        "الأحوال الشخصية", "الأسرة", "مدونة الأسرة", "صداق", "طلاق", "تقادم", "تعويض"
    ]

    # --- MATCHING LOGIC ---
    # We check the Title first because it yields the highest accuracy for classification.
    
    # Constitutional Check
    if any(x in title_lower for x in constitutional_keywords):
        return "Constitutional"
    # Criminal Check
    elif any(x in title_lower for x in criminal_keywords):
        return "Criminal"
    # Business Check
    elif any(x in title_lower for x in business_keywords):
        return "Business"
    # Civil Check
    elif any(x in title_lower for x in civil_keywords):
        return "Civil"
        
    # --- FALLBACK TO BODY TEXT MATCHING ---
    # If the title doesn't yield a clear category, search the broader text body.
    elif any(x in text_lower for x in constitutional_keywords):
        return "Constitutional"
    elif any(x in text_lower for x in criminal_keywords):
        return "Criminal"
    elif any(x in text_lower for x in business_keywords):
        return "Business"
    elif any(x in text_lower for x in civil_keywords):
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
        title = art.get("doc_title", "Unknown")
        text = art.get("text", "")
        
        group = classify_law(title, text)
        
        flattened_articles.append({
            "id": art.get("id"),  # Unique ID generated during chunking
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
    
    # Fire the payload into Docker and block until processing finishes
    task = index.add_documents(flattened_articles)
    client.wait_for_task(task.task_uid)
    print(f"   ✅ Success! Uploaded {len(flattened_articles)} articles from {os.path.basename(filepath)}.")

if __name__ == "__main__":
    index = client.index("moroccan_laws")
    
    # 🧹 Wipes out every document inside this index first to prevent duplicate entries
    print("🧹 Cleaning up existing index...")
    task = index.delete_all_documents()
    client.wait_for_task(task.task_uid)
    print("🗑️ All old documents successfully deleted!")

    # ⚙️ Configure filtering attributes ONCE at index initialization
    print("⚙️ Applying search settings to Meilisearch...")
    index.update_filterable_attributes(["group", "language", "type"])

    # 🚀 Scan folder and upload all json files
    if not os.path.exists(JSON_INPUT_DIR):
        print(f"❌ Error: The directory '{JSON_INPUT_DIR}' does not exist. Run Chunker.py first!")
    else:
        json_files = [f for f in os.listdir(JSON_INPUT_DIR) if f.lower().endswith('.json')]
        
        if not json_files:
            print(f"❌ No JSON files found in '{JSON_INPUT_DIR}'.")
        else:
            print(f"🚀 Starting data injection pipeline for {len(json_files)} files...")
            print("-" * 50)
            
            for file_name in json_files:
                full_path = os.path.join(JSON_INPUT_DIR, file_name)
                try:
                    index_ramis_production_data(full_path)
                except Exception as e:
                    print(f"❌ Error during injection of {file_name}: {e}")
                    
            print("-" * 50)
            print("🎉 Pipeline run complete! All legal articles successfully loaded.")