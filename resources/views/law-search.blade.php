<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marokko Biz | Moroccan Law Search</title>
    <link rel="icon" href="/marokko-biz-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/law-search.css">
  </head>
  <body>
    <main class="app-shell">
      <header class="topbar">
        <a class="brand" href="/">
          <img src="/marokko-biz-icon.png" alt="Marokko Biz">
          <span>Marokko Biz Law Search</span>
        </a>
        <div class="status-pill">
          <span class="status-dot"></span>
          Indexed corpus
        </div>
        <nav class="nav-links" aria-label="Corpus navigation">
          <a href="/corpus/status">Corpus status</a>
        </nav>
      </header>

      <section class="workspace">
        <section class="search-panel">
          <div class="section-kicker">Indexed Moroccan legal corpus</div>
          <h1>Search laws, articles, references, and sources.</h1>
          <p class="lead">Search the official and legacy sources currently indexed by this Laravel rebuild. Coverage represents available indexed sources only.</p>

          <form class="search-form" id="search-form">
            <input id="search-input" name="q" type="search" autocomplete="off" placeholder="Try immobilier, Code du travail, Article 6, Loi 31-08">
            <button type="submit">Search</button>
          </form>

          <div class="quick-searches" aria-label="Quick searches">
            <button type="button" data-query="immobilier">Immobilier</button>
            <button type="button" data-query="travail">Travail</button>
            <button type="button" data-query="commerce">Commerce</button>
            <button type="button" data-query="famille">Famille</button>
            <button type="button" data-query="fiscalite">Fiscalite</button>
          </div>
        </section>

        <aside class="overview-panel">
          <div class="section-kicker">Indexed corpus overview</div>
          <div class="stat-grid">
            <article>
              <strong id="total-articles">0</strong>
              <span>Articles</span>
            </article>
            <article>
              <strong id="total-documents">0</strong>
              <span>Documents</span>
            </article>
            <article>
              <strong id="total-categories">0</strong>
              <span>Areas</span>
            </article>
          </div>
          <div class="category-list" id="category-list"></div>
        </aside>
      </section>

      <section class="results-section">
        <div class="section-head">
          <div>
            <div class="section-kicker">Results</div>
            <h2 id="results-title">Ready to search</h2>
          </div>
          <span id="result-count" class="count-pill">0 results</span>
        </div>
          <div id="search-state" class="state-panel">Search available sources by topic, code, article, or law reference.</div>
        <div id="results-list" class="results-list"></div>
      </section>

      <section class="chat-panel">
        <div class="section-head">
          <div>
            <div class="section-kicker">Legal assistant</div>
            <h2>Ask a legal search question</h2>
          </div>
        </div>
        <div id="chat-feed" class="chat-feed">
          <div class="chat-message assistant">Hi. Ask about a Moroccan legal topic and I will search the indexed corpus.</div>
        </div>
        <form id="chat-form" class="chat-form">
          <textarea id="chat-input" rows="3" placeholder="Example: what laws apply to commercial leases?"></textarea>
          <button type="submit">Send</button>
        </form>
      </section>
    </main>

    <script src="/js/law-search.js"></script>
  </body>
</html>
