import streamlit as st
import meilisearch
import re
from supabase import create_client, Client

# Set up page configuration
st.set_page_config(
    page_title="Moroccan Jurisprudence Search Engine",
    page_icon="⚖️",
    layout="wide"
)

# --- Supabase Connection ---
# Replace these strings with your actual Supabase Project details
SUPABASE_URL = "https://qcmxickkatwibhmulwoh.supabase.co"
SUPABASE_KEY = "sb_publishable_aPHvANtuewM93nihH93WbQ_khnsthi7"

@st.cache_resource
def get_supabase() -> Client:
    return create_client(SUPABASE_URL, SUPABASE_KEY)

# Connect to Services
supabase = get_supabase()

@st.cache_resource
def get_meilisearch_client():
    return meilisearch.Client("http://localhost:7700")

client = get_meilisearch_client()
index = client.index("moroccan_laws")

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
        text-align: center;
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
        text-align: center;
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
            border-left: 4px solid #90cdf4 !important;
        }
    }
    
    .highlight {
        background-color: #fef08a !important; 
        color: #000000 !important;
        font-weight: bold;
        padding: 2px 4px;
        border-radius: 4px;
    }
    </style>
""", unsafe_allow_html=True)

# --- Helper Functions ---
def highlight_search_terms(text, query):
    if not query:
        return text
    words = re.escape(query).split(r'\ ')
    pattern = r'(' + '|'.join(words) + r')'
    return re.sub(pattern, r'<mark class="highlight">\1</mark>', text, flags=re.IGNORECASE)

def is_valid_referral(code: str) -> bool:
    # Matches exactly 3 uppercase letters (e.g., MAR, POL)
    return bool(re.match(r"^[A-Z]{3}$", code))


# Initialize Auth Session State
if "user" not in st.session_state:
    st.session_state["user"] = None

# --- GATEWAY: SIGN IN & SIGN UP FORMS ---
if not st.session_state["user"]:
    st.markdown('<div class="main-title">⚖️ Moroccan Law Search Portal</div>', unsafe_allow_html=True)
    st.markdown('<div class="subtitle">Secure Jurisprudence & Official Bulletin Vault</div>', unsafe_allow_html=True)
    
    # Elegant tabs for Auth flow
    tab_login, tab_register = st.tabs(["🔐 Sign In", "📝 Create Account"])
    
    with tab_login:
        st.write("")
        login_email = st.text_input("Email Address", key="login_email")
        login_password = st.text_input("Password", type="password", key="login_password")
        
        if st.button("Log In", use_container_width=True):
            try:
                # Attempt to authenticate via Supabase
                response = supabase.auth.sign_in_with_password({"email": login_email, "password": login_password})
                st.session_state["user"] = response.user
                st.success("Successfully logged in!")
                st.rerun()
            except Exception as e:
                # If email isn't confirmed yet, Supabase natively blocks the sign-in and sends an error
                st.error(f"Authentication Error: {e}")
                
    with tab_register:
        st.write("")
        reg_name = st.text_input("Full Name", placeholder="e.g., Slimani Reda")
        reg_email = st.text_input("Email Address", placeholder="your.email@domain.com")
        reg_password = st.text_input("Password", type="password", help="Minimum 6 characters")
        
        # 3-Letter Referral Code Field
        referral_code = st.text_input("Referral Code", placeholder="Must be 3 uppercase letters (e.g., MAR)")
        
        if st.button("Register Account", use_container_width=True):
            # 1. Enforce Referral Code format rule
            if not is_valid_referral(referral_code):
                st.error("❌ Invalid Referral Code! It must be exactly three uppercase letters (e.g., XYZ).")
            elif not reg_email or not reg_password or not reg_name:
                st.error("❌ All fields are required to register.")
            else:
                try:
                    # 2. Register user through Supabase with custom metadata parameters
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
                    st.success("🎉 Success! An email confirmation link has been sent to your inbox. Please verify your email before logging in.")
                except Exception as e:
                    st.error(f"Registration Error: {e}")

# --- MAIN APP: DYNAMIC SEARCH PORTAL ---
else:
    # Sidebar User Profile & Sign Out
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

    # Main Dashboard Page
    st.markdown('<div class="main-title" style="text-align: left;">⚖️ Moroccan Law Search Engine</div>', unsafe_allow_html=True)
    st.markdown('<div class="subtitle" style="text-align: left;">Official Bilingual Portal for Institutional Decrees, Codes, and Dahirs</div>', unsafe_allow_html=True)
    st.markdown("---")

    # --- Sidebar Filters ---
    st.sidebar.markdown("### 🎛️ Search Filters")

    lang_choice = st.sidebar.multiselect(
        "Language / اللغة",
        options=["ar", "fr"],
        default=["ar", "fr"],
        format_func=lambda x: "العربية (ar)" if x == "ar" else "Français (fr)"
    )

    categories = ["Constitutional", "Criminal", "Business", "Civil", "Uncategorized"]
    selected_cats = st.sidebar.multiselect(
        "Legal Category",
        options=categories,
        default=categories
    )

    doc_types = ["Dahir", "Décret", "Loi"]
    selected_types = st.sidebar.multiselect(
        "Document Type",
        options=doc_types,
        default=doc_types
    )

    # --- Main Search Bar ---
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
                st.markdown(f"📊 **Found {len(hits)} matching clauses**")
                st.write("")
                
                for hit in hits:
                    raw_text = hit.get('text', '')
                    highlighted_text = highlight_search_terms(raw_text, search_query)
                    
                    card_html = f"""
                    <div class="law-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 15px;">
                            <h4 style="margin: 0; color: var(--text-color); font-weight: 600; font-size:1.2rem;">{hit.get('document_title')}</h4>
                            <span class="badge badge-category">{hit.get('group')}</span>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <span class="badge badge-type">📄 {hit.get('type')}</span>
                            <span class="badge badge-lang">🌐 {hit.get('language').upper()}</span>
                            <span style="font-size: 0.9rem; color: gray; margin-left: 10px;"><b>Section:</b> {hit.get('path', 'Article')}</span>
                        </div>
                    """
                    
                    if hit.get('language') == 'ar':
                        card_html += f'<div class="arabic-text">{highlighted_text}</div></div>'
                    else:
                        card_html += f'<div class="french-text">{highlighted_text}</div></div>'
                        
                    st.markdown(card_html, unsafe_allow_html=True)
                    
        except Exception as e:
            st.error(f"Error querying database index: {e}")
    else:
        st.markdown("""
        <div style="background-color: rgba(59, 130, 246, 0.1); border-left: 5px solid #3b82f6; padding: 20px; border-radius: 5px; margin-top:20px;">
            <h4 style="margin-top: 0;">💡 Welcome to your Legal Workspace</h4>
            <p style="margin-bottom: 0; opacity: 0.9;">Type your key phrase into the search box above. The layout and themes will continue to adapt to your light or dark mode system preference automatically.</p>
        </div>
        """, unsafe_allow_html=True)