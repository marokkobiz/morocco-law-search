<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marokko Biz | Corpus Status</title>
    <link rel="icon" href="/icons/a.png">
    <link rel="stylesheet" href="/css/law-search.css">
  </head>
  <body>
    @php
      $latestImport = $status['latestImportDate']
          ? \Illuminate\Support\Carbon::parse($status['latestImportDate'])->format('Y-m-d H:i')
          : 'No imports yet';
    @endphp

    <main class="app-shell">
      <header class="topbar">
        <a class="brand" href="/">
          <img src="/icons/a.png" alt="Marokko Biz">
          <span>Marokko Biz Legal Corpus</span>
        </a>
        <nav class="nav-links" aria-label="Corpus navigation">
          <a href="/">Search</a>
          <a href="/corpus/status" aria-current="page">Corpus status</a>
        </nav>
      </header>

      <section class="search-panel">
        <div class="section-kicker">Indexed corpus status</div>
        <h1>Source-based legal corpus.</h1>
        <p class="lead">This dashboard tracks the Moroccan legal sources currently indexed by this app. It represents available indexed sources only.</p>
        <div class="status-banner">{{ $status['warning'] }}</div>
      </section>

      <section class="overview-panel corpus-status-panel">
        <div class="section-kicker">Current index</div>
        <div class="stat-grid corpus-stat-grid">
          <article>
            <strong>{{ number_format($status['totalSources']) }}</strong>
            <span>Sources</span>
          </article>
          <article>
            <strong>{{ number_format($status['totalDocuments']) }}</strong>
            <span>Documents</span>
          </article>
          <article>
            <strong>{{ number_format($status['totalVersions']) }}</strong>
            <span>Versions</span>
          </article>
          <article>
            <strong>{{ number_format($status['totalArticles']) }}</strong>
            <span>Articles</span>
          </article>
          <article>
            <strong>{{ number_format($status['totalChunks']) }}</strong>
            <span>Search chunks</span>
          </article>
          <article>
            <strong>{{ $latestImport }}</strong>
            <span>Latest import</span>
          </article>
        </div>
      </section>

      <section class="workspace corpus-workspace">
        <section class="results-section">
          <div class="section-head">
            <div>
              <div class="section-kicker">Coverage by source</div>
              <h2>Available sources</h2>
            </div>
          </div>
          <div class="data-table">
            <div class="data-table-row data-table-head">
              <span>Type</span>
              <span>Sources</span>
              <span>Documents</span>
              <span>Articles</span>
            </div>
            @forelse ($status['coverageBySource'] as $row)
              <div class="data-table-row">
                <span>{{ $row['sourceType'] ?: 'unknown' }}</span>
                <span>{{ number_format($row['sourceCount']) }}</span>
                <span>{{ number_format($row['documentCount']) }}</span>
                <span>{{ number_format($row['articleCount']) }}</span>
              </div>
            @empty
              <div class="state-panel">No indexed sources yet.</div>
            @endforelse
          </div>
        </section>

        <aside class="overview-panel">
          <div class="section-kicker">Coverage by domain</div>
          <div class="category-list">
            @forelse ($status['coverageByDomain'] as $row)
              <div class="category-row">
                <span>{{ $row['domain'] ?: 'general' }}</span>
                <strong>{{ number_format($row['articleCount']) }}</strong>
              </div>
            @empty
              <div class="state-panel">No domains indexed yet.</div>
            @endforelse
          </div>
        </aside>
      </section>

      <section class="results-section">
        <div class="section-head">
          <div>
            <div class="section-kicker">Import runs</div>
            <h2>Update pipeline</h2>
          </div>
        </div>
        <div class="data-table">
          <div class="data-table-row data-table-head">
            <span>Run</span>
            <span>Status</span>
            <span>Documents</span>
            <span>Articles</span>
            <span>Errors</span>
          </div>
          @forelse ($status['latestRuns'] as $run)
            <div class="data-table-row">
              <span>#{{ $run['id'] }} {{ $run['importType'] }}</span>
              <span>{{ $run['status'] }}</span>
              <span>{{ number_format($run['documentsImported']) }}</span>
              <span>{{ number_format($run['articlesExtracted']) }}</span>
              <span>{{ number_format($run['errorCount']) }}</span>
            </div>
          @empty
            <div class="state-panel">No import runs recorded yet.</div>
          @endforelse
        </div>
      </section>
    </main>
  </body>
</html>
