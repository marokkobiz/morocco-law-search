# Maroc Law Crawler

Application de crawling et de recherche des lois marocaines à partir de deux sources officielles :

1. **SGG** (Secrétariat Général du Gouvernement) — [sgg.gov.ma](https://www.sgg.gov.ma/Accueil.aspx)
2. **Adala** (Portail Juridique du Ministère de la Justice) — [adala.justice.gov.ma](https://adala.justice.gov.ma/fr)

## Fonctionnalités

- 🔍 **Crawling automatique** des textes juridiques depuis les deux sources
- 💾 **Stockage structuré** des lois dans une base SQLite
- 🔎 **Moteur de recherche** full-text avec filtres (source, type de texte)
- 🌐 **Interface web** Flask pour la recherche interactive
- 📊 **Statistiques** sur les textes indexés

## Structure du projet

```
Crawler/
├── main.py                  # Point d'entrée du programme
├── config.py                # Configuration des URLs et paramètres
├── requirements.txt         # Dépendances Python
├── README.md                # Ce fichier
├── Explication.md           # Document d'explication du code
├── data/                    # Base de données SQLite
│   └── maroc_lois.db
├── crawlers/
│   ├── __init__.py
│   ├── sgg_crawler.py       # Crawler pour le site SGG
│   └── adala_crawler.py     # Crawler pour le site Adala
├── database/
│   ├── __init__.py
│   └── db_manager.py        # Gestionnaire de base de données SQLite
├── search/
│   ├── __init__.py
│   └── search_engine.py     # Moteur de recherche
├── web_interface/
│   ├── __init__.py
│   ├── app.py               # Application Flask
│   └── templates/
│       ├── index.html       # Page d'accueil
│       └── results.html     # Page de résultats
└── downloads/               # Téléchargements (optionnel)
```

## Installation

```bash
# Cloner ou copier le projet
cd Crawler

# Installer les dépendances
pip install -r requirements.txt
```

## Utilisation

```bash
# Lancer le crawling des deux sites
python main.py crawl

# Démarrer l'interface web (après crawling)
python main.py serve

# Mode recherche en ligne de commande
python main.py search

# Crawling + interface web
python main.py all

# Afficher l'aide
python main.py help
```

## API REST

Une fois le serveur démarré, les endpoints suivants sont disponibles :

| Endpoint | Description |
|----------|-------------|
| `GET /` | Page d'accueil avec statistiques |
| `GET /search?q=terme&source=SGG&type=Loi` | Recherche de textes |
| `GET /api/search?q=terme` | API JSON de recherche |
| `GET /api/stats` | API JSON des statistiques |

## Sources

### SGG (Secrétariat Général du Gouvernement)
- Bulletin Officiel du Royaume du Maroc
- Textes consolidés
- Textes importants
- Recherche dans les sommaires du BO

### Adala (Portail Juridique)
- Textes législatifs et réglementaires
- Ressources thématiques
- Nouveautés législatives
- Projets de lois

## Licence

Projet à but éducatif. Les données appartiennent aux institutions gouvernementales marocaines.
