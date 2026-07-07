# Explication détaillée du code

## Architecture générale

Le projet suit une architecture modulaire avec 4 composants principaux :

```
main.py (orchestrateur)
├── crawlers/          → Extraction des données
│   ├── sgg_crawler    → Crawling du site SGG
│   └── adala_crawler  → Crawling du site Adala
├── database/          → Persistance des données
│   └── db_manager     → Gestionnaire SQLite
├── search/            → Recherche
│   └── search_engine  → Moteur de recherche
└── web_interface/     → Interface utilisateur
    ├── app.py         → Application Flask
    └── templates/     → Pages HTML
```

---

## 1. `config.py` — Configuration

Fichier central de configuration qui définit :

- **URLs des sites** : Les endpoints principaux des deux sites gouvernementaux
- **Chemin de la base de données** : `data/maroc_lois.db`
- **User-Agent** : En-tête HTTP pour éviter le blocage
- **Timeout** : 30 secondes pour les requêtes HTTP

---

## 2. `database/db_manager.py` — Gestionnaire de base de données

### Rôle
Gère toute la persistance des données dans une base SQLite.

### Structure de la table `laws`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INTEGER | Clé primaire auto-incrémentée |
| `title` | TEXT | Titre du texte juridique |
| `type` | TEXT | Type (Dahir, Loi, Décret, etc.) |
| `number` | TEXT | Numéro officiel du texte |
| `date_gregorian` | TEXT | Date grégorienne |
| `date_islamic` | TEXT | Date hégirienne |
| `source` | TEXT | Source (SGG ou Adala) |
| `source_url` | TEXT | URL de la page source |
| `law_url` | TEXT | URL directe vers le texte |
| `summary` | TEXT | Résumé du contenu |
| `content` | TEXT | Contenu complet |
| `theme` | TEXT | Thème juridique |
| `raw_html` | TEXT | HTML brut pour référence |
| `created_at` | TIMESTAMP | Date d'indexation |

### Méthodes principales
- `init_db()` : Crée les tables et les index
- `insert_law(law)` : Insère une loi (IGNORE si doublon)
- `search_laws(query, ...)` : Recherche full-text avec filtres et pagination
- `get_stats()` : Statistiques par source et types
- `update_crawler_state()` : Suivi de l'état du crawling

### Index SQL
Des index sont créés sur `title`, `type`, `source` et `theme` pour accélérer les recherches.

---

## 3. `crawlers/sgg_crawler.py` — Crawler SGG

### Défi technique
Le site SGG utilise **ASP.NET WebForms** avec des postbacks et un état de vue (ViewState). Le crawler doit :
1. Charger la page de recherche pour obtenir le ViewState
2. Extraire les champs cachés (`__VIEWSTATE`, `__VIEWSTATEGENERATOR`, `__EVENTVALIDATION`)
3. Soumettre le formulaire avec ces champs pour effectuer une recherche

### Fonctionnement
```
_search_page() → extrait ViewState
      ↓
search(query) → POST avec ViewState + critères
      ↓
parse_results() → Extrait les lois du HTML retourné
      ↓
insert dans la base de données
```

### Méthodes
- `_get_viewstate(html)` : Extrait les champs cachés ASP.NET
- `_get_search_page()` : Récupère la page de recherche initiale
- `search(query, nature, date_deb, date_fin, page_size)` : Effectue une recherche
- `parse_results(html)` : Parse les résultats HTML en dictionnaires structurés
- `crawl_keyword(keyword)` : Recherche un mot-clé et stocke les résultats
- `crawl_textes_consolides()` : Parcourt la section "Textes consolidés"
- `crawl_textes_importants()` : Parcourt la section "Textes importants"
- `run_full_crawl(keywords)` : Lance le crawl complet avec une liste de mots-clés prédéfinis

### Mots-clés de recherche par défaut
Le crawler utilise 18 mots-clés thématiques : droit, loi, code, travail, famille, impôt, commercial, pénal, civil, foncier, eau, environnement, investissement, social, éducation, santé, agriculture, énergie.

---

## 4. `crawlers/adala_crawler.py` — Crawler Adala

### Défi technique
Le site Adala est une **application Next.js** (React) avec rendu serveur (SSR). Le crawler utilise :
1. Les URLs de recherche SSR (`/fr/search?term=...`) qui retournent du HTML complet
2. Le parsing HTML des résultats de recherche
3. Le crawl des pages "Ressources" et "Nouveautés"

### Fonctionnement
```
search(term, page) → GET sur /fr/search?term=...
      ↓
parse_search_results() → Extrait les lois du HTML
      ↓
insert dans la base de données
```

### Méthodes
- `search(query, page, resources, themes, law_types)` : Recherche avec filtres
- `parse_search_results(html)` : Parse les résultats en extrayant titre, type, thème, date, URL
- `crawl_keyword(keyword, max_pages)` : Recherche un mot-clé sur plusieurs pages
- `crawl_resources()` : Parcourt les ressources thématiques
- `crawl_new_releases()` : Parcourt les nouveautés législatives
- `run_full_crawl(keywords)` : Lance le crawl complet

---

## 5. `search/search_engine.py` — Moteur de recherche

### Rôle
Couche d'abstraction entre la base de données et l'interface utilisateur.

### Méthodes
- `search(query, sources, law_types, page, per_page)` : Recherche avec filtres
- `get_stats()` : Retourne les statistiques globales
- `get_filters()` : Retourne la liste des sources et types disponibles
- `search_by_theme(theme)` : Recherche par thème

### Recherche full-text
La recherche utilise SQLite avec `LIKE '%terme%'` sur les colonnes `title`, `summary`, `content`, `theme` et `type`. Les termes sont séparés et recherchés individuellement (ET logique).

---

## 6. `web_interface/app.py` — Application Flask

### Routes
- `GET /` : Page d'accueil avec statistiques et formulaire de recherche
- `GET /search` : Page de résultats avec pagination et filtres
- `GET /api/search` : API JSON pour la recherche
- `GET /api/stats` : API JSON pour les statistiques

### Templates HTML
Les templates utilisent uniquement du CSS inline (pas de framework externe) pour rester autonomes.

---

## 7. `main.py` — Point d'entrée

### Commandes
| Commande | Action |
|----------|--------|
| `python main.py crawl` | Lance le crawling complet des deux sites |
| `python main.py search` | Mode interactif en ligne de commande |
| `python main.py serve` | Démarre le serveur web Flask |
| `python main.py all` | Crawl puis démarre le serveur |
| `python main.py help` | Affiche l'aide |

---

## Flux de données complet

```
Sites web (SGG + Adala)
    ↓ Crawling (requests + BeautifulSoup)
Base de données SQLite (laws table)
    ↓ Indexation
Moteur de recherche (LIKE full-text)
    ↓
Interface web Flask / API JSON
    ↓
Utilisateur final
```


