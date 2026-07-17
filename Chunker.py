import os
import re
import json
import uuid

# Configuration
TXT_INPUT_DIR = "./extracted_laws/"   # The folder where your text files are saved
JSON_OUTPUT_DIR = "./json_laws/"       # Where your article-level JSON files will go

# Robust bilingual regex patterns to catch article headers
# Matches: "Article 1", "Article premier", "المادة 12", "الفصل 3", "14 الفصل"
ARTICLE_PATTERNS = [
    r'(?i)^\s*Article\s+(?:premier|1er|\d+)\b',     # French: Article 1, Article premier
    r'^\s*(?:المادة|الفصل)\s*(?:\d+|[\u0660-\u0669]+)\b', # Arabic: المادة 1, الفصل ١٢
    r'^\s*(?:\d+|[\u0660-\u0669]+)\s+(?:الفصل|المادة)\b'  # Alternative Arabic layout (e.g. 2011 Constitution)
]

# Combined pattern for splitting
SPLIT_PATTERN = re.compile("|".join(ARTICLE_PATTERNS), re.MULTILINE)

def contains_arabic(text):
    return bool(re.search(r'[\u0600-\u06FF]', text))

def clean_footnotes(text: str) -> str:
    """
    Finds footnote boundaries (lines of underscores, dashes, or numbered lists like '2 - ...')
    at the bottom of a text block and strips them away.
    """
    # 1. Split on clear horizontal separator lines (e.g., ________ or ---------)
    # These are highly reliable indicators that footnotes have started.
    parts = re.split(r'\n\s*_{3,}\s*\n|\n\s*-{3,}\s*\n', text)
    text_cleaned = parts[0].strip()
    
    # 2. Fallback check: If there's no visual separator line, look for footnote list markers
    # near the end of the text block (e.g., a line starting with '2 -' or Arabic '٢ -').
    cleaned_lines = []
    for line in text_cleaned.splitlines():
        # Match lines starting with optional whitespace, a number (Bilingual), a dash/dot, and actual text
        if re.match(r'^\s*[\d\u0660-\u0669]+\s*[-.]\s*[A-Za-zÀ-ÿأ-ي]', line):
            # We hit the footnote section! Ignore this and everything after it.
            break
        cleaned_lines.append(line)
        
    return "\n".join(cleaned_lines).strip()

def chunk_text_into_articles(file_content, filename):
    """
    Splits the document text into articles, preserving headers and content,
    and structures them for Meilisearch ingestion.
    """
    # Detect language of this document
    lang = "ar" if contains_arabic(file_content) else "fr"
    doc_title = os.path.splitext(filename)[0]
    
    # 1. Find all article start positions in the text
    matches = list(SPLIT_PATTERN.finditer(file_content))
    
    articles = []
    
    # If no article headers are matched, default to saving the entire text as one chunk
    if not matches:
        articles.append({
            "id": str(uuid.uuid4()),
            "doc_title": doc_title,
            "text": clean_footnotes(file_content),  # Clean footnotes here too
            "doc_language": lang,
            "doc_type": "Dahir",
            "doc_source_file": filename,
            "article_num": "Full Text"
        })
        return articles

    # 2. Extract preamble (everything before the first matched article, if any exists)
    preamble_text = file_content[:matches[0].start()].strip()
    if preamble_text and len(preamble_text) > 20:
        articles.append({
            "id": str(uuid.uuid4()),
            "doc_title": doc_title,
            "text": clean_footnotes(preamble_text),  # Clean footnotes out of the preamble
            "doc_language": lang,
            "doc_type": "Dahir",
            "doc_source_file": filename,
            "article_num": "Preamble"
        })

    # 3. Extract and pair each article header with its body
    for i in range(len(matches)):
        start_idx = matches[i].start()
        # End index is either the start of the next match or the end of the text file
        end_idx = matches[i+1].start() if i + 1 < len(matches) else len(file_content)
        
        full_chunk = file_content[start_idx:end_idx].strip()
        
        # Extract the specific article header (e.g., "المادة 1" or "Article 5")
        header_match = matches[i].group(0).strip()
        
        # Normalize the article number string for easier querying later
        article_num = re.sub(r'^(Article|المادة|الفصل)\s*', '', header_match, flags=re.IGNORECASE).strip()

        # Apply the footnote cleaning logic directly to the article text block
        pure_article_text = clean_footnotes(full_chunk)

        articles.append({
            "id": str(uuid.uuid4()),
            "doc_title": doc_title,
            "text": pure_article_text,
            "doc_language": lang,
            "doc_type": "Dahir",
            "doc_source_file": filename,
            "article_num": article_num
        })
        
    return articles

def run_chunker_pipeline():
    if not os.path.exists(JSON_OUTPUT_DIR):
        os.makedirs(JSON_OUTPUT_DIR)

    txt_files = [f for f in os.listdir(TXT_INPUT_DIR) if f.lower().endswith('.txt')]
    if not txt_files:
        print(f"No text files found in '{TXT_INPUT_DIR}'. Did you run Parser.py first?")
        return

    print(f"Found {len(txt_files)} file(s) to split.\n" + "-"*40)

    for txt_file in txt_files:
        txt_path = os.path.join(TXT_INPUT_DIR, txt_file)
        json_filename = os.path.splitext(txt_file)[0] + ".json"
        json_path = os.path.join(JSON_OUTPUT_DIR, json_filename)
        
        print(f"Splitting: {txt_file}...")
        
        try:
            with open(txt_path, 'r', encoding='utf-8') as f:
                file_content = f.read()
                
            # Run the extraction split logic
            chunked_articles = chunk_text_into_articles(file_content, txt_file)
            
            # Save the structured list of articles directly into a flat JSON format
            with open(json_path, 'w', encoding='utf-8') as out_f:
                json.dump(chunked_articles, out_f, ensure_ascii=False, indent=4)
                
            print(f"   🎉 Split into {len(chunked_articles)} articles ➡️ {json_filename}\n")
            
        except Exception as e:
            print(f"   ❌ Error splitting {txt_file}: {e}\n")

if __name__ == "__main__":
    run_chunker_pipeline()