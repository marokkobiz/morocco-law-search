from crawlers.sgg_crawler import SGGCrawler
from crawlers.adala_crawler import AdalaCrawler
from database.db_manager import DatabaseManager
from config import DB_PATH

db = DatabaseManager(DB_PATH)

print("=" * 60)
print("TEST DES CRAWLERS")
print("=" * 60)

print("\n[1] Test SGG crawler...")
sgg = SGGCrawler(db)
try:
    laws = sgg.crawl_keyword("travail", max_pages=2)
    print(f"  -> {len(laws)} lois trouvées sur SGG")
    if laws:
        print(f"  -> Première: {laws[0]['title'][:80]}")
        print(f"  -> Type: {laws[0]['type']}")
except Exception as e:
    print(f"  -> ERREUR: {e}")

print("\n[2] Test Adala crawler...")
adala = AdalaCrawler(db)
try:
    laws = adala.crawl_keyword("travail", max_pages=2)
    print(f"  -> {len(laws)} lois trouvées sur Adala")
    if laws:
        print(f"  -> Première: {laws[0]['title'][:80]}")
        print(f"  -> Type: {laws[0]['type']}")
except Exception as e:
    print(f"  -> ERREUR: {e}")

print("\n[3] Statistiques finales:")
stats = db.get_stats()
print(f"  Total: {stats['total']}")
print(f"  Par source: {stats['by_source']}")

print("\nTest terminé.")
