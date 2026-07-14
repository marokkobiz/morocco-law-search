import streamlit as st
import meilisearch
import re

# Set up page configuration
st.set_page_config(
    page_title="Moroccan Jurisprudence Search Engine",
    page_icon="⚖️",
    layout="wide"
)

# --- Custom Dynamic CSS Styling (Adapts to Light/Dark Mode) ---
st.markdown("""
    <style>
    /* Main App Background & Text */
    .stApp {
        background-color: var(--background-color) !important;
        color: var(--text-color) !important;
    }
    
    /* Elegant Title Header Styling */
    .main-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: #1a365d; /* Elegant deep blue for light mode */
        margin-bottom: 5px;
    }
    
    /* Adjust main title color to be highly visible in dark mode */
    @media (prefers-color-scheme: dark) {
        .main-title {
            color: #90cdf4 !important; /* Soft light blue for dark mode */
        }
    }
    
    .subtitle {
        font-size: 1.1rem;
        color: var(--text-color);
        opacity: 0.8;
        margin-bottom: 25px;
    }
    
    /* Result Card Container Styling */
    .law-card {
        background-color: var(--secondary-background-color) !important;
        border: 1px solid rgba(226, 232, 240, 0.2) !important;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: var(--text-color) !important;
    }
    
    /* Badge styling */
    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-category { 
        background-color: rgba(226, 232, 240, 0.2) !important; 
        color: var(--text-color) !important; 
    }
    .badge-type { 
        background-color: rgba(254, 235, 200, 0.2) !important; 
        color: #dd6b20 !important; 
    } 
    .badge-lang { 
        background-color: rgba(235, 248, 255, 0.2) !important; 
        color: #3182ce !important; 
    }
    
    /* Text block styles */
    .arabic-text {
        text-align: right !important; 
        direction: rtl !important; 
        font-family: 'Cairo', 'Segoe UI', sans-serif;
        font-size: 1.15rem; 
        line-height: 1.8;
        background-color: rgba(0, 0, 0, 0.02) !important; 
        color: var(--text-color) !important;
        padding: 18px; 
        border-radius: 8px;
        border-right: 4px solid #d69e2e !important; /* Moroccan Gold Trim */
    }
    
    /* Dark mode adjustments for Arabic text block background */
    @media (prefers-color-scheme: dark) {
        .arabic-text {
            background-color: rgba(255, 255, 255, 0.05) !important;
        }
    }
    
    .french-text {
        text-align: left !important; 
        font-size: 1.05rem; 
        line-height: 1.6;
        background-color: rgba(0, 0, 0, 0.02) !important; 
        color: var(--text-color) !important;
        padding: 18px; 
        border-radius: 8px;
        border-left: 4px solid #1a365d !important; /* Royal Navy Trim */
    }
    
    @media (prefers-color-scheme: dark) {
        .french-text {
            background-color: rgba(255, 255, 255, 0.05) !important;
            border-left: 4px solid #90cdf4 !important; /* Light blue trim for dark mode */
        }
    }
    
    /* Search Term Highlight style */
    .highlight {
        background-color: #fef08a !important; /* Soft gold marker highlight */
        color: #000000 !important; /* Keep highlight text black for readability */
        font-weight: bold;
        padding: 2px 4px;
        border-radius: 4px;
    }
    </style>
""", unsafe_allow_html=True)



# Connect to Meilisearch Docker container
@st.cache_resource
def get_meilisearch_client():
    return meilisearch.Client("http://localhost:7700")

client = get_meilisearch_client()
index = client.index("moroccan_laws")

# Helper function to dynamically highlight matched terms in the text
def highlight_search_terms(text, query):
    if not query:
        return text
    # Escape special characters and find all occurrences seamlessly case-insensitive
    words = re.escape(query).split(r'\ ')
    pattern = r'(' + '|'.join(words) + r')'
    return re.sub(pattern, r'<mark class="highlight">\1</mark>', text, flags=re.IGNORECASE)

# --- UI Header ---
st.markdown('<div class="main-title">⚖️ Moroccan Law Search Engine</div>', unsafe_allow_html=True)
st.markdown('<div class="subtitle">Official Bilingual Portal for Institutional Decrees, Codes, and Dahirs</div>', unsafe_allow_html=True)
st.markdown("---")

# --- Sidebar Filters ---
st.sidebar.markdown("### 🎛️ Search Filters")

# 1. Language Filter
lang_choice = st.sidebar.multiselect(
    "Language / اللغة",
    options=["ar", "fr"],
    default=["ar", "fr"],
    format_func=lambda x: "العربية (ar)" if x == "ar" else "Français (fr)"
)

# 2. Category Filter
categories = ["Constitutional", "Criminal", "Business", "Civil", "Uncategorized"]
selected_cats = st.sidebar.multiselect(
    "Legal Category",
    options=categories,
    default=categories
)

# 3. Document Type Filter
doc_types = ["Dahir", "Décret", "Loi"]
selected_types = st.sidebar.multiselect(
    "Document Type",
    options=doc_types,
    default=doc_types
)

# --- Main Search Bar ---
st.write("### 🚨 IF YOU CAN SEE THIS RED TEXT, YOUR APP UPDATED SUCCESSFULLY 🚨")
search_query = st.text_input("🔍 Search across legal documentation...", placeholder="Type keywords here (e.g., الساعة القانونية, obligations, عقود)...", label_visibility="collapsed")

# --- Build Meilisearch Filter Query ---
filter_clauses = []
if lang_choice:
    filter_clauses.append(f"language IN [{', '.join([f'\"{l}\"' for l in lang_choice])}]")
if selected_cats:
    filter_clauses.append(f"group IN [{', '.join([f'\"{c}\"' for c in selected_cats])}]")
if selected_types:
    filter_clauses.append(f"type IN [{', '.join([f'\"{t}\"' for t in selected_types])}]")

filter_string = " AND ".join(filter_clauses) if filter_clauses else ""

# --- Execute Search & Display Results ---
if search_query:
    try:
        search_results = index.search(search_query, {
            "filter": filter_string,
            "limit": 20
        })
        
        hits = search_results.get("hits", [])
        
        if not hits:
            st.warning("No matching clauses discovered. Try broadening your keywords.")
        else:
            # Metric Counter Layout
            st.markdown(f"📊 **Found {len(hits)} matching clauses**")
            st.write("")
            
            for hit in hits:
                # Highlight keywords dynamically inside the text segment
                raw_text = hit.get('text', '')
                highlighted_text = highlight_search_terms(raw_text, search_query)
                
                # HTML Card generation for crisp UI layout control
                card_html = f"""
                <div class="law-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 15px;">
                        <h4 style="margin: 0; color: #1a365d; font-weight: 600; font-size:1.2rem;">{hit.get('document_title')}</h4>
                        <span class="badge badge-category">{hit.get('group')}</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <span class="badge badge-type">📄 {hit.get('type')}</span>
                        <span class="badge badge-lang">🌐 {hit.get('language').upper()}</span>
                        <span style="font-size: 0.9rem; color:#718096; margin-left: 10px;"><b>Section:</b> {hit.get('path', 'Article')}</span>
                    </div>
                """
                
                # Render textual core body respecting alignment directions
                if hit.get('language') == 'ar':
                    card_html += f'<div class="arabic-text">{highlighted_text}</div></div>'
                else:
                    card_html += f'<div class="french-text">{highlighted_text}</div></div>'
                    
                st.markdown(card_html, unsafe_allow_html=True)
                
    except Exception as e:
        st.error(f"Error querying database index: {e}")
else:
    # Beautiful, empty-state placeholder card when no query is typed yet
    st.markdown("""
    <div style="background-color: #eff6ff; border-left: 5px solid #3b82f6; padding: 20px; border-radius: 5px; margin-top:20px;">
        <h4 style="color: #1e40af; margin-top: 0;">💡 Pro-Tip for Prototyping</h4>
        <p style="color: #1e3a8a; margin-bottom: 0;">Type a key phrase like <b>"الساعة"</b> to observe the automated Arabic text-alignment or try filtering parameters using the left sidebar panel.</p>
    </div>
    """, unsafe_allow_html=True)