def classify_law(title, text_sample):
    title_lower = title.lower()
    text_lower = text_sample.lower()
    
    # 1. Constitutional
    if "constitution" in title_lower or "الدستور" in title_lower:
        return "Constitutional"
    
    # 2. Criminal
    elif any(x in title_lower for x in ["pénal", "général", "جنائي", "العقوبات", "زجري"]):
        return "Criminal"
    
    # 3. Business / Commercial
    elif any(x in title_lower for x in ["commerce", "sociétés", "fiscal", "تجاري", "الشركات", "التجارة"]):
        return "Business"
    
    # 4. Civil (Default fallback if it's obligations, contracts, family, etc.)
    else:
        return "Civil"

def flatten_for_indexing(rami_json_data):
    search_ready_articles = []
    doc_info = rami_json_data["document"]
    
    # Grab a sample of text to help with classification if title is vague
    first_article_text = rami_json_data["articles"][0]["text"] if rami_json_data["articles"] else ""
    group = classify_law(doc_info["title"], first_article_text)
    
    for art in rami_json_data["articles"]:
        flattened_article = {
            # Unique ID combining document title and article number
            "id": f"{doc_info['title']}_art_{art['article_number']}".replace(" ", "_"),
            "group": group,
            "document_title": doc_info["title"],
            "language": doc_info["language"],
            "type": doc_info["type"],
            "article_number": art["article_number"],
            "chapter": art["context"]["chapter"],
            "section": art["context"]["section"],
            "text": art["text"]
        }
        search_ready_articles.append(flattened_article)
        
    return search_ready_articles