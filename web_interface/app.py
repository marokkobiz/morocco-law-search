from flask import Flask, render_template, request, jsonify
from database.db_manager import DatabaseManager
from search.search_engine import SearchEngine


def create_app(db_path):
    app = Flask(__name__)
    db = DatabaseManager(db_path)
    engine = SearchEngine(db)

    @app.route("/")
    def index():
        stats = engine.get_stats()
        filters = engine.get_filters()
        return render_template(
            "index.html", stats=stats, filters=filters
        )

    @app.route("/search")
    def search():
        query = request.args.get("q", "").strip()
        sources = request.args.getlist("source")
        law_types = request.args.getlist("type")
        page = request.args.get("page", 1, type=int)

        if not query and not sources and not law_types:
            return render_template(
                "index.html",
                stats=engine.get_stats(),
                filters=engine.get_filters(),
                error="Veuillez entrer un terme de recherche.",
            )

        results = engine.search(
            query=query,
            sources=sources if sources else None,
            law_types=law_types if law_types else None,
            page=page,
            per_page=20,
        )

        filters = engine.get_filters()

        return render_template(
            "results.html",
            results=results,
            query=query,
            selected_sources=sources,
            selected_types=law_types,
            filters=filters,
            stats=engine.get_stats(),
        )

    @app.route("/api/search")
    def api_search():
        query = request.args.get("q", "").strip()
        sources = request.args.getlist("source")
        law_types = request.args.getlist("type")
        page = request.args.get("page", 1, type=int)

        results = engine.search(
            query=query,
            sources=sources if sources else None,
            law_types=law_types if law_types else None,
            page=page,
            per_page=20,
        )

        return jsonify(results)

    @app.route("/api/stats")
    def api_stats():
        return jsonify(engine.get_stats())

    return app
