import sqlite3
import os


class DatabaseManager:
    def __init__(self, db_path):
        self.db_path = db_path
        os.makedirs(os.path.dirname(db_path), exist_ok=True)
        self.init_db()

    def get_connection(self):
        conn = sqlite3.connect(self.db_path)
        conn.row_factory = sqlite3.Row
        return conn

    def init_db(self):
        conn = self.get_connection()
        cursor = conn.cursor()
        cursor.executescript("""
            CREATE TABLE IF NOT EXISTS laws (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                type TEXT,
                number TEXT,
                date_gregorian TEXT,
                date_islamic TEXT,
                source TEXT NOT NULL,
                source_url TEXT,
                law_url TEXT,
                summary TEXT,
                content TEXT,
                theme TEXT,
                raw_html TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX IF NOT EXISTS idx_laws_title ON laws(title);
            CREATE INDEX IF NOT EXISTS idx_laws_type ON laws(type);
            CREATE INDEX IF NOT EXISTS idx_laws_source ON laws(source);
            CREATE INDEX IF NOT EXISTS idx_laws_theme ON laws(theme);

            CREATE TABLE IF NOT EXISTS crawler_state (
                source TEXT PRIMARY KEY,
                last_crawled TIMESTAMP,
                status TEXT,
                total_count INTEGER DEFAULT 0
            );
        """)
        conn.commit()
        conn.close()

    def insert_law(self, law):
        conn = self.get_connection()
        cursor = conn.cursor()
        cursor.execute(
            """INSERT OR IGNORE INTO laws 
            (title, type, number, date_gregorian, date_islamic, source, source_url, law_url, summary, content, theme, raw_html)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            (
                law.get("title", ""),
                law.get("type", ""),
                law.get("number", ""),
                law.get("date_gregorian", ""),
                law.get("date_islamic", ""),
                law.get("source", ""),
                law.get("source_url", ""),
                law.get("law_url", ""),
                law.get("summary", ""),
                law.get("content", ""),
                law.get("theme", ""),
                law.get("raw_html", ""),
            ),
        )
        conn.commit()
        conn.close()

    def law_exists(self, title, source):
        conn = self.get_connection()
        cursor = conn.cursor()
        cursor.execute(
            "SELECT COUNT(*) FROM laws WHERE title = ? AND source = ?",
            (title, source),
        )
        count = cursor.fetchone()[0]
        conn.close()
        return count > 0

    def search_laws(self, query, sources=None, law_types=None, page=1, per_page=20):
        conn = self.get_connection()
        cursor = conn.cursor()

        conditions = []
        params = []

        terms = query.strip().split()
        for term in terms:
            conditions.append(
                "(title LIKE ? OR summary LIKE ? OR content LIKE ? OR theme LIKE ? OR type LIKE ?)"
            )
            params.extend([f"%{term}%"] * 5)

        if sources:
            placeholders = ",".join("?" for _ in sources)
            conditions.append(f"source IN ({placeholders})")
            params.extend(sources)

        if law_types:
            placeholders = ",".join("?" for _ in law_types)
            conditions.append(f"type IN ({placeholders})")
            params.extend(law_types)

        where_clause = " AND ".join(conditions) if conditions else "1=1"

        count_query = f"SELECT COUNT(*) FROM laws WHERE {where_clause}"
        cursor.execute(count_query, params)
        total = cursor.fetchone()[0]

        offset = (page - 1) * per_page
        data_query = f"""
            SELECT * FROM laws 
            WHERE {where_clause} 
            ORDER BY date_gregorian DESC, created_at DESC 
            LIMIT ? OFFSET ?
        """
        cursor.execute(data_query, params + [per_page, offset])
        rows = [dict(row) for row in cursor.fetchall()]

        conn.close()
        return {"total": total, "page": page, "per_page": per_page, "data": rows}

    def get_stats(self):
        conn = self.get_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT source, COUNT(*) as count FROM laws GROUP BY source")
        by_source = {row["source"]: row["count"] for row in cursor.fetchall()}
        cursor.execute("SELECT COUNT(*) as total FROM laws")
        total = cursor.fetchone()["total"]
        cursor.execute("SELECT DISTINCT type FROM laws WHERE type != '' ORDER BY type")
        types = [row["type"] for row in cursor.fetchall()]
        conn.close()
        return {"total": total, "by_source": by_source, "types": types}

    def get_distinct_sources(self):
        conn = self.get_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT DISTINCT source FROM laws ORDER BY source")
        sources = [row["source"] for row in cursor.fetchall()]
        conn.close()
        return sources

    def get_distinct_types(self):
        conn = self.get_connection()
        cursor = conn.cursor()
        cursor.execute(
            "SELECT DISTINCT type FROM laws WHERE type != '' ORDER BY type"
        )
        types = [row["type"] for row in cursor.fetchall()]
        conn.close()
        return types

    def update_crawler_state(self, source, status, total_count=0):
        conn = self.get_connection()
        cursor = conn.cursor()
        cursor.execute(
            """INSERT OR REPLACE INTO crawler_state (source, last_crawled, status, total_count)
            VALUES (?, datetime('now'), ?, ?)""",
            (source, status, total_count),
        )
        conn.commit()
        conn.close()

    def get_crawler_state(self, source):
        conn = self.get_connection()
        cursor = conn.cursor()
        cursor.execute(
            "SELECT * FROM crawler_state WHERE source = ?", (source,)
        )
        row = cursor.fetchone()
        conn.close()
        return dict(row) if row else None
