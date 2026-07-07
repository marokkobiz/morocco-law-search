from database.db_manager import DatabaseManager


class SearchEngine:
    def __init__(self, db_manager):
        self.db = db_manager

    def search(self, query, sources=None, law_types=None, page=1, per_page=20):
        return self.db.search_laws(
            query=query,
            sources=sources,
            law_types=law_types,
            page=page,
            per_page=per_page,
        )

    def get_stats(self):
        return self.db.get_stats()

    def get_filters(self):
        sources = self.db.get_distinct_sources()
        types = self.db.get_distinct_types()
        return {"sources": sources, "types": types}

    def search_by_theme(self, theme, page=1, per_page=20):
        return self.db.search_laws(
            query=theme, page=page, per_page=per_page
        )
