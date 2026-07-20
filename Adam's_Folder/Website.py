import streamlit as st
import meilisearch
import re
from supabase import create_client, Client

# Set up page configuration
st.set_page_config(
    page_title="MarocLoi.com | LOI-pedia Online",
    page_icon="⚖️",
    layout="wide"
)

# --- Supabase Connection ---
SUPABASE_URL = "https://qcmxickkatwibhmulwoh.supabase.co"
SUPABASE_KEY = "sb_publishable_aPHvANtuewM93nihH93WbQ_khnsthi7"

@st.cache_resource
def get_supabase() -> Client:
    return create_client(SUPABASE_URL, SUPABASE_KEY)

# Connect to Services
supabase = get_supabase()

@st.cache_resource
def get_meilisearch_client():
    return meilisearch.Client("http://localhost:7800")

client = get_meilisearch_client()
index = client.index("moroccan_laws")

# --- Custom Premium CSS (Matches your MarocLoi.com Design) ---
st.markdown("""
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap');

    /* Global Body and Background Styling */
    .stApp {
        background: radial-gradient(circle at 10% 20%, #102a43 0%, #0b1a30 90%) !important;
        color: #f0f4f8 !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
    }

    /* Remove default Streamlit top padding and header space */
    header, [data-testid="stHeader"] {
        background-color: transparent !important;
    }
    
    .block-container {
        padding-top: 1.5rem !important;
    }

    /* --- Navigation Header --- */
    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0px;
        margin-bottom: 40px;
    }
    .brand-logo {
        display: flex;
        align-items: center;
        font-size: 1.5rem;
        font-weight: 700;
        color: #ffffff;
        text-decoration: none;
    }
    .brand-logo span {
        color: #3b82f6;
    }
    .nav-links {
        display: flex;
        gap: 30px;
    }
    .nav-link {
        color: #9fb3c8;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }
    .nav-link:hover {
        color: #ffffff;
    }
    .nav-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .lang-dropdown {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.9rem;
    }
    .dashboard-btn {
        background: #1d4ed8;
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
    }
    .dashboard-btn:hover {
        background: #2563eb;
    }

    /* --- Hero Layout Elements --- */
    .pill-badge {
        display: inline-flex;
        align-items: center;
        background: rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.25);
        color: #60a5fa;
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 25px;
    }
    .pill-badge::before {
        content: "●";
        margin-right: 6px;
        font-size: 0.7rem;
        color: #3b82f6;
    }
    
    .hero-title {
        font-family: 'Playfair Display', serif !important;
        font-size: 4rem !important;
        line-height: 1.1 !important;
        font-weight: 700 !important;
        color: #ffffff !important;
        margin-bottom: 20px;
    }

    .hero-subtitle {
        font-size: 1.15rem;
        line-height: 1.6;
        color: #9fb3c8;
        margin-bottom: 35px;
        max-width: 540px;
    }

    /* --- Sleek Search Input Box Styling --- */
    /* Streamlit targets */
    div[data-testid="stTextInput"] input {
        background-color: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
        border-radius: 50px !important;
        padding: 15px 25px 15px 50px !important;
        font-size: 1.1rem !important;
        transition: all 0.3s ease;
    }
    
    div[data-testid="stTextInput"] input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.25) !important;
    }

    /* --- Right Hand Rounded Image --- */
    .hero-image-container {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
    }
    .hero-image {
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
        width: 100%;
        max-width: 500px;
        object-fit: cover;
    }

    /* Result Card Container Styling */
    .law-card {
        background-color: rgba(255, 255, 255, 0.03) !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        color: #f0f4f8 !important;
        backdrop-filter: blur(8px);
    }
    
    /* Result card text blocks */
    .arabic-text {
        text-align: right !important; 
        direction: rtl !important; 
        font-family: 'Cairo', sans-serif;
        font-size: 1.15rem; 
        line-height: 1.8;
        background-color: rgba(255, 255, 255, 0.02) !important; 
        color: #ffffff !important;
        padding: 18px; 
        border-radius: 8px;
        border-right: 4px solid #d69e2e !important;
    }
    .french-text {
        text-align: left !important; 
        font-size: 1.05rem; 
        line-height: 1.6;
        background-color: rgba(255, 255, 255, 0.02) !important; 
        color: #f0f4f8 !important;
        padding: 18px; 
        border-radius: 8px;
        border-left: 4px solid #3b82f6 !important;
    }
    
    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-category { background-color: rgba(255, 255, 255, 0.1) !important; color: #ffffff !important; }
    .badge-type { background-color: rgba(254, 235, 200, 0.1) !important; color: #f6ad55 !important; } 
    .badge-lang { background-color: rgba(235, 248, 255, 0.1) !important; color: #63b3ed !important; }

    .highlight {
        background-color: rgba(59, 130, 246, 0.3) !important;
        color: #ffffff !important;
        font-weight: bold;
        padding: 2px 4px;
        border-radius: 4px;
        border-bottom: 2px solid #3b82f6;
    }
    </style>
""", unsafe_allow_html=True)

# --- Helper Functions ---
def highlight_search_terms(text: str, query: str) -> str:
    if not query or not query.strip() or not text:
        return text or ""

    clean_q = query.strip()
    
    # Escape the full query so special characters don't break regex
    pattern = re.escape(clean_q)

    # Highlight only the exact sequence typed by the user
    return re.sub(
        f"({pattern})",
        r'<mark style="background-color: #2563eb; color: white; padding: 2px 4px; border-radius: 4px;">\1</mark>',
        text,
        flags=re.IGNORECASE
    )

def is_valid_referral(code: str) -> bool:
    return bool(re.match(r"^[A-Z]{3}$", code))

# Initialize Auth Session State
if "user" not in st.session_state:
    st.session_state["user"] = None

# --- TOP NAVIGATION BAR (HTML) ---
st.markdown("""
    <div class="navbar">
        <a class="brand-logo" href="#">⚖️ Maroc<span>Loi.com</span></a>
        <div class="nav-links">
            <a class="nav-link" href="#">About</a>
            <a class="nav-link" href="#">Sources</a>
            <a class="nav-link" href="#">Coverage</a>
        </div>
        <div class="nav-actions">
            <select class="lang-dropdown">
                <option>EN</option>
                <option>AR</option>
                <option>FR</option>
            </select>
            <a class="dashboard-btn" href="#">Dashboard</a>
        </div>
    </div>
""", unsafe_allow_html=True)

# --- GATEWAY: SIGN IN / SIGN UP & DASHBOARD ---
if not st.session_state["user"]:
    
    # 2-Column Landing Page Hero Setup
    hero_col_left, hero_col_right = st.columns([1.2, 1])
    
    with hero_col_left:
        st.markdown('<div class="pill-badge">Loi-pedia Online</div>', unsafe_allow_html=True)
        st.markdown('<div class="hero-title">Moroccan<br>LOI-pedia<br>Online</div>', unsafe_allow_html=True)
        st.markdown('<div class="hero-subtitle">Search Moroccan legislation, official bulletins, legal texts, and source-backed analysis from one professional workspace.</div>', unsafe_allow_html=True)
        
        # Modern Landing Page Login/Registration flow within the Hero Col
        tab_login, tab_register = st.tabs(["🔐 Sign In", "📝 Create Account"])
        
        with tab_login:
            login_email = st.text_input("Email Address", key="login_email")
            login_password = st.text_input("Password", type="password", key="login_password")
            if st.button("Log In", use_container_width=True):
                try:
                    response = supabase.auth.sign_in_with_password({"email": login_email, "password": login_password})
                    st.session_state["user"] = response.user
                    st.success("Successfully logged in!")
                    st.rerun()
                except Exception as e:
                    st.error(f"Authentication Error: {e}")
                    
        with tab_register:
            reg_name = st.text_input("Full Name", placeholder="e.g., Slimani Reda")
            reg_email = st.text_input("Email Address", placeholder="your.email@domain.com")
            reg_password = st.text_input("Password", type="password")
            referral_code = st.text_input("Referral Code", placeholder="e.g., MAR")
            
            if st.button("Register Account", use_container_width=True):
                if not is_valid_referral(referral_code):
                    st.error("❌ Invalid Referral Code! Must be 3 uppercase letters.")
                elif not reg_email or not reg_password or not reg_name:
                    st.error("❌ All fields are required.")
                else:
                    try:
                        response = supabase.auth.sign_up({
                            "email": reg_email,
                            "password": reg_password,
                            "options": {
                                "data": {
                                    "full_name": reg_name,
                                    "referral_code": referral_code
                                }
                            }
                        })
                        st.success("🎉 Success! Check your inbox for the confirmation email.")
                    except Exception as e:
                        st.error(f"Registration Error: {e}")
                        
    with hero_col_right:
        # Beautiful matching right-column graphic placeholder
        st.markdown("""
            <div class="hero-image-container">
                <img class="hero-image" src="https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&q=80&w=1000" alt="Legal Gavel and House Scale">
            </div>
        """, unsafe_allow_html=True)

# --- PORTAL ACTIVE WORKSPACE ---
else:
    user_metadata = st.session_state["user"].user_metadata
    user_name = user_metadata.get("full_name", "User")
    user_ref = user_metadata.get("referral_code", "N/A")
    
    st.sidebar.markdown(f"### 👋 Ahlan, **{user_name}**!")
    st.sidebar.caption(f"Ref Code: `{user_ref}`")
    
    if st.sidebar.button("Log Out", use_container_width=True):
        supabase.auth.sign_out()
        st.session_state["user"] = None
        st.rerun()
        
    st.sidebar.markdown("---")
    st.sidebar.markdown("### 🎛️ Search Filters")

    lang_choice = st.sidebar.multiselect("Language / اللغة", options=["ar", "fr"], default=["ar", "fr"])
    categories = ["Constitutional", "Criminal", "Business", "Civil", "Uncategorized"]
    selected_cats = st.sidebar.multiselect("Legal Category", options=categories, default=categories)
    doc_types = ["Dahir", "Décret", "Loi"]
    selected_types = st.sidebar.multiselect("Document Type", options=doc_types, default=doc_types)

    # Clean UI search layout
    st.markdown('<div class="pill-badge">WORKSPACE PORTAL</div>', unsafe_allow_html=True)
    st.markdown('<div class="hero-title" style="font-size:3rem !important;">Moroccan LOI-pedia Workspace</div>', unsafe_allow_html=True)
    
    search_query = st.text_input("🔍 Search across legal documentation...", placeholder="Search article, code, bulletin, or legal issue...", label_visibility="collapsed")

    # Build filters
    filter_clauses = []
    if lang_choice:
        filter_clauses.append(f"language IN [{', '.join([f'\"{l}\"' for l in lang_choice])}]")
    if selected_cats:
        filter_clauses.append(f"group IN [{', '.join([f'\"{c}\"' for c in selected_cats])}]")
    if selected_types:
        filter_clauses.append(f"type IN [{', '.join([f'\"{t}\"' for t in selected_types])}]")

    filter_string = " AND ".join(filter_clauses) if filter_clauses else ""

    # Display Results
    if search_query and search_query.strip():
        try:
            clean_q = search_query.strip()
            exact_query = f'"{clean_q}"'
        
            search_results = index.search(
                exact_query, 
                {
                    "filter": filter_string, 
                    "matchingStrategy": "all",
                    "limit": 50
                }
            )
            raw_hits = search_results.get("hits", [])
            
            # Strict Python post-filter to ensure exact sentence match
            hits = [
                h for h in raw_hits 
                if clean_q.lower() in h.get('text', '').lower() or clean_q.lower() in h.get('document_title', '').lower()
            ]
            
            if not hits:
                st.warning("No matching clauses discovered for exact sequence.")
            else:
                st.markdown(f"📊 **Found {len(hits)} matching clauses**")
                for hit in hits:
                    raw_text = hit.get('text', '')
                    doc_title = hit.get('document_title', '')
                    
                    highlighted_text = highlight_search_terms(raw_text, search_query)
                    highlighted_title = highlight_search_terms(doc_title, search_query)
                    
                    card_html = f"""
                    <div class="law-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 15px;">
                            <h4 style="margin: 0; color: #ffffff; font-weight: 600; font-size:1.2rem;">{highlighted_title}</h4>
                            <span class="badge badge-category">{hit.get('group')}</span>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <span class="badge badge-type">📄 {hit.get('type')}</span>
                            <span class="badge badge-lang">🌐 {str(hit.get('language')).upper()}</span>
                            <span style="font-size: 0.9rem; color: #9fb3c8; margin-left: 10px;"><b>Section:</b> {hit.get('path', 'Article')}</span>
                        </div>
                    """
                    if hit.get('language') == 'ar':
                        card_html += f'<div class="arabic-text">{highlighted_text}</div></div>'
                    else:
                        card_html += f'<div class="french-text">{highlighted_text}</div></div>'
                        
                    st.markdown(card_html, unsafe_allow_html=True)
        except Exception as e:
            st.error(f"Error querying database index: {e}")