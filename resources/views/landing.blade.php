<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moroccan Legal Research | Marokko Biz</title>
    <link rel="icon" href="/marokko-biz-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,650;9..144,760&family=Manrope:wght@400;500;600;700;800;900&display=swap"
      rel="stylesheet"
    >
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body class="font-sans antialiased">
    <main class="blue-home">
      <header class="blue-home-nav">
        <a href="/" class="blue-home-brand" aria-label="Marokko Biz Legal Research">
          <img src="/marokko-biz-icon.png" alt="Marokko Biz">
          <span>Marokko Biz</span>
        </a>

        <nav aria-label="Homepage navigation">
          <a href="https://www.marokkobiz.com/">About Us</a>
        </nav>

        <div class="blue-home-account">
          <a href="/login">Login</a>
          <a href="/register">Create account</a>
        </div>
      </header>

      <section class="blue-home-hero">
        <div class="blue-home-copy">
          <p class="blue-home-kicker">Professional legal research</p>
          <h1>Moroccan Legal Research Engine</h1>
          <p>
            Search Moroccan legislation, official bulletins, legal texts, and source-backed analysis from one professional workspace.
          </p>
          <form class="blue-hero-search" action="/login" method="get">
            <label class="sr-only" for="hero-search">Search legal texts</label>
            <input
              id="hero-search"
              name="q"
              type="search"
              placeholder="Search article, code, bulletin, source or legal issue..."
            >
            <button type="submit">Search</button>
          </form>
        </div>
      </section>

      <section id="sources" class="blue-home-section">
        <div>
          <span>Sources</span>
          <h2>Official materials stay visible.</h2>
        </div>
        <div class="blue-card-grid">
          <article>
            <strong>Official bulletins</strong>
            <p>Publication metadata, dates, source URLs and indexed document versions.</p>
          </article>
          <article>
            <strong>Legal texts</strong>
            <p>Codes, dahirs, decrees, orders and article-level references.</p>
          </article>
          <article>
            <strong>Citations</strong>
            <p>Results show source links and article references clearly.</p>
          </article>
        </div>
      </section>

      <section id="coverage" class="blue-home-section blue-split-section">
        <div>
          <span>Coverage</span>
          <h2>Built for trust, not overclaiming.</h2>
          <p>Coverage depends on indexed official sources. The platform communicates available sources, not all Moroccan laws.</p>
        </div>
        <div class="blue-proof-card">
          <strong>Indexed corpus</strong>
          <span>Active versions preferred</span>
          <span>Legacy fallback labeled</span>
          <span>Official sources visible</span>
        </div>
      </section>

      <section class="blue-home-section">
        <div>
          <span>Indexed database</span>
          <h2>Available Sources</h2>
        </div>
        <div class="blue-audience-grid">
          <article>Bulletin Officiel</article>
          <article>Code du Travail</article>
          <article>Code Penal</article>
          <article>Code de la Famille</article>
          <article>DOC</article>
          <article>Immobilier</article>
        </div>
      </section>

      <footer class="blue-home-footer">
        Legal information from indexed sources. Not a substitute for legal advice.
      </footer>
    </main>
  </body>
</html>
