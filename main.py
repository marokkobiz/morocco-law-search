import sys
import time
from database.db_manager import DatabaseManager
from crawlers.sgg_crawler import SGGCrawler
from crawlers.adala_crawler import AdalaCrawler
from search.search_engine import SearchEngine
from config import DB_PATH


def run_crawl():
    print("=" * 60)
    print("CRAWLER DES LOIS MAROCAINES")
    print("=" * 60)

    db = DatabaseManager(DB_PATH)

    crawlers_list = []

    print("\n[1/2] Crawling SGG (Secrétariat Général du Gouvernement)...")
    sgg = SGGCrawler(db)
    try:
        sgg_laws = sgg.run_full_crawl()
        crawlers_list.append(("SGG", len(sgg_laws)))
        print(f"  -> {len(sgg_laws)} lois trouvées sur SGG")
    except Exception as e:
        print(f"  -> ERREUR SGG: {e}")

    print("\n[2/2] Crawling Adala (Portail Juridique du Ministère de la Justice)...")
    adala = AdalaCrawler(db)
    try:
        adala_laws = adala.run_full_crawl()
        crawlers_list.append(("Adala", len(adala_laws)))
        print(f"  -> {len(adala_laws)} lois trouvées sur Adala")
    except Exception as e:
        print(f"  -> ERREUR Adala: {e}")

    stats = db.get_stats()
    print("\n" + "=" * 60)
    print("RÉSUMÉ DU CRAWLING")
    print("=" * 60)
    print(f"Total des lois dans la base: {stats['total']}")
    for source, count in stats["by_source"].items():
        print(f"  - {source}: {count} lois")
    print(f"Types de textes: {', '.join(stats['types'])}")
    print("=" * 60)

    return stats


def search_loop():
    db = DatabaseManager(DB_PATH)
    engine = SearchEngine(db)

    print("\nMode recherche interactif. Tapez 'quit' pour quitter.\n")
    while True:
        query = input("\n🔍 Rechercher: ").strip()
        if query.lower() in ("quit", "q", "exit"):
            break
        if not query:
            continue

        results = engine.search(query, per_page=10)
        print(f"\n📋 {results['total']} résultat(s) trouvé(s):\n")
        for i, law in enumerate(results["data"], 1):
            print(f"  {i}. [{law['source']}] {law['type']} - {law['title'][:100]}")
            if law["theme"]:
                print(f"     Thème: {law['theme']}")
            print(f"     URL: {law['law_url']}")
            print()


def run_search_server():
    from web_interface.app import create_app
    app = create_app(DB_PATH)
    print("\n" + "=" * 60)
    print("Démarrage du serveur web d'interface...")
    print("Ouvrez http://127.0.0.1:5000 dans votre navigateur")
    print("=" * 60)
    app.run(debug=True, host="0.0.0.0", port=5000)


def print_help():
    print("""
Maroc Law Crawler - Utilisation
================================

Commandes:
  python main.py crawl     - Lancer le crawling des deux sites
  python main.py search    - Mode recherche en ligne de commande
  python main.py serve     - Démarrer l'interface web Flask
  python main.py all       - Crawl + serveur web
  python main.py help      - Afficher cette aide
""")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print_help()
        sys.exit(0)

    command = sys.argv[1].lower()

    if command == "crawl":
        run_crawl()
    elif command == "search":
        search_loop()
    elif command == "serve":
        run_search_server()
    elif command == "all":
        run_crawl()
        run_search_server()
    else:
        print_help()
