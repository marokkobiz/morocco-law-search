import os
import sys
import subprocess
import time

# Automatically determine the project root directory
PROJECT_ROOT = os.path.dirname(os.path.abspath(__file__))

def run_python_script(script_name: str, args: list = None) -> bool:
    """Helper function to run a python script as a subprocess and wait for it to finish."""
    script_path = os.path.join(PROJECT_ROOT, script_name)
    
    if not os.path.exists(script_path):
        print(f"❌ Error: Could not find script at {script_path}")
        return False
    
    cmd = [sys.executable, "-u", script_path]
    if args:
        cmd.extend(args)
        
    try:
        process = subprocess.Popen(cmd, stdout=sys.stdout, stderr=sys.stderr)
        process.wait()
        return process.returncode == 0
    except Exception as e:
        print(f"❌ Exception occurred while running {script_name}: {e}")
        return False

def start_crawler_background():
    """
    Launches the spider in the background so it doesn't block the rest of the pipeline.
    Assumes it's either a Scrapy spider ('scrapy crawl laws_spider') or a python file ('laws_spider.py').
    """
    print("\n🕷️ Launching Crawler (laws_spider) in the background...")
    print("=" * 60)
    
    scrapy_cfg_path = os.path.join(PROJECT_ROOT, "scrapy.cfg")
    
    try:
        if os.path.exists(scrapy_cfg_path) or os.path.exists(os.path.join(PROJECT_ROOT, "spiders")):
            process = subprocess.Popen(["scrapy", "crawl", "laws_spider"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        elif os.path.exists(os.path.join(PROJECT_ROOT, "laws_spider.py")):
            process = subprocess.Popen([sys.executable, "laws_spider.py"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        else:
            print("⚠️ Could not find 'laws_spider' script or Scrapy project. Skipping background crawl...")
            return None
            
        print("✅ Crawler is running in the background.")
        return process
    except Exception as e:
        print(f"❌ Failed to launch background crawler: {e}")
        return None

def start_streamlit(script_name: str):
    """Launches the Streamlit frontend as a background process."""
    script_path = os.path.join(PROJECT_ROOT, script_name)
    if not os.path.exists(script_path):
        print(f"❌ Error: Could not find Streamlit file at {script_path}")
        return
        
    print(f"\n🚀 Launching Streamlit Workspace Dashboard: {script_name}...")
    print("=" * 60)
    try:
        subprocess.Popen([sys.executable, "-m", "streamlit", "run", script_path])
        print("🎉 Streamlit running at http://localhost:8501!")
    except Exception as e:
        print(f"❌ Failed to launch Streamlit: {e}")

def main():
    print("==========================================================")
    print("   ⚖️ MAROCLOI.COM - CONTINUOUS SEARCH PIPELINE ⚖️")
    print("==========================================================")
    
    # 1. Start the crawler in the background (non-blocking)
    crawler_process = start_crawler_background()
    
    # 2. Launch your Streamlit frontend dashboard (non-blocking)
    start_streamlit("Website.py")
    
    print("\n🔄 Starting continuous background compilation...")
    print("   The Parser, Chunker, and Categorizer will run every 2 minutes to process new laws.")
    print("   Press CTRL+C in this terminal to stop the entire pipeline.")
    print("=" * 60)

    json_dir = os.path.join(PROJECT_ROOT, "json_laws")

    # 3. Infinite loop to keep updating your database with new files
    try:
        while True:
            print(f"\n[{time.strftime('%H:%M:%S')}] 🔄 Checking for new laws to process...")
            
            # Step 1: Run Parser.py to extract text from raw scraped files/PDFs
            print("   ↳ Running Parser...")
            parser_success = run_python_script("Parser.py")
            
            # Step 2: Run Chunker.py to clean footnotes and split into JSON articles
            if parser_success:
                print("   ↳ Running Chunker...")
                chunker_success = run_python_script("Chunker.py")
            else:
                chunker_success = False
                
            # Step 3: Run Categorizer.py if new JSON articles exist
            has_json_files = os.path.exists(json_dir) and any(f.endswith('.json') for f in os.listdir(json_dir))
            
            if chunker_success and has_json_files:
                print("   ↳ Running Categorizer to update Meilisearch...")
                run_python_script("Categorizer.py")
            else:
                print("   ℹ️ No new data processed yet. Skipping Categorizer update to protect database.")
                
            # Check if background crawler has finished
            if crawler_process and crawler_process.poll() is not None:
                print("\nℹ️ Crawler process has finished running!")
                crawler_process = None
                
            # Interval: Sleep for 120 seconds (2 minutes)
            time.sleep(120)
            
    except KeyboardInterrupt:
        print("\n\n🛑 Shutting down pipeline orchestrator...")
        if crawler_process and crawler_process.poll() is None:
            print("🕷️ Terminating background crawler...")
            crawler_process.terminate()
        print("👋 Goodbye!")

if __name__ == "__main__":
    main()