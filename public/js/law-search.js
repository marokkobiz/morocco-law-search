const formatNumber = (value) => new Intl.NumberFormat("en-US").format(Number(value || 0));

const categoryLabels = {
  banking: "Banking",
  civil: "Civil law",
  commercial: "Commercial law",
  consumer: "Consumer protection",
  criminal: "Criminal law",
  education: "Education",
  energy: "Energy",
  environment: "Environment",
  family: "Family law",
  health: "Health",
  insurance: "Insurance",
  labor: "Labor",
  "public-procurement": "Public procurement",
  "real-estate": "Real estate",
  tax: "Tax"
};

const formatCategory = (category) =>
  categoryLabels[category] ||
  String(category || "general")
    .split("-")
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");

const state = {
  currentQuery: "",
  results: []
};

const elements = {
  searchForm: document.querySelector("#search-form"),
  searchInput: document.querySelector("#search-input"),
  totalArticles: document.querySelector("#total-articles"),
  totalDocuments: document.querySelector("#total-documents"),
  totalCategories: document.querySelector("#total-categories"),
  categoryList: document.querySelector("#category-list"),
  resultsTitle: document.querySelector("#results-title"),
  resultCount: document.querySelector("#result-count"),
  searchState: document.querySelector("#search-state"),
  resultsList: document.querySelector("#results-list"),
  chatForm: document.querySelector("#chat-form"),
  chatInput: document.querySelector("#chat-input"),
  chatFeed: document.querySelector("#chat-feed")
};

const setSearchState = (message, visible = true) => {
  elements.searchState.textContent = message;
  elements.searchState.classList.toggle("is-hidden", !visible);
};

const renderOverview = (overview) => {
  elements.totalArticles.textContent = formatNumber(overview.totalArticles);
  elements.totalDocuments.textContent = formatNumber(overview.totalDocuments);
  elements.totalCategories.textContent = formatNumber(overview.totalCategories);

  elements.categoryList.innerHTML = "";
  (overview.categories || []).slice(0, 16).forEach((category) => {
    const row = document.createElement("div");
    row.className = "category-row";
    row.innerHTML = `<span>${formatCategory(category.category)}</span><strong>${formatNumber(category.articleCount)}</strong>`;
    elements.categoryList.append(row);
  });
};

const renderResults = (payload) => {
  const results = payload.results || [];
  state.results = results;

  elements.resultsTitle.textContent = payload.query ? `Results for "${payload.query}"` : "Ready to search";
  elements.resultCount.textContent = payload.hasMore ? `Top ${payload.limit}` : `${formatNumber(payload.count)} results`;
  elements.resultsList.innerHTML = "";

  if (!results.length) {
    setSearchState("No matching indexed articles found. Try a broader French legal term or a specific reference.");
    return;
  }

  setSearchState("", false);

  results.forEach((law) => {
    const card = document.createElement("article");
    card.className = "result-card";
    card.innerHTML = `
      <span class="category-chip">${formatCategory(law.category)}</span>
      <h3>${escapeHtml(law.title)}</h3>
      <div class="result-meta">
        <span>${escapeHtml(law.article_number || "")}</span>
        <span>${escapeHtml(law.document_title || "")}</span>
        <span>${escapeHtml(law.law_reference || "")}</span>
      </div>
      <p class="article-preview">${escapeHtml(law.content || "")}</p>
      <div class="source-line">
        <span>${escapeHtml(law.source_name || "Official legal source")}</span>
        ${law.source_url ? `<a href="${escapeAttribute(law.source_url)}" target="_blank" rel="noreferrer">Open source</a>` : ""}
      </div>
      <div class="action-row">
        <button type="button" data-translate="${law.id}" data-target="en">English</button>
        <button type="button" data-translate="${law.id}" data-target="ar">Arabic</button>
      </div>
      <div class="translation-box is-hidden" id="translation-${law.id}"></div>
    `;
    elements.resultsList.append(card);
  });
};

const search = async (query) => {
  const normalizedQuery = String(query || "").trim();

  if (!normalizedQuery) {
    return;
  }

  state.currentQuery = normalizedQuery;
  elements.searchInput.value = normalizedQuery;
  setSearchState("Searching...");
  elements.resultsList.innerHTML = "";

  const response = await fetch(`/api/laws/search?q=${encodeURIComponent(normalizedQuery)}`);
  if (!response.ok) {
    throw new Error("Search failed");
  }

  renderResults(await response.json());
};

const translate = async (lawId, targetLanguage) => {
  const box = document.querySelector(`#translation-${lawId}`);

  box.classList.remove("is-hidden");
  box.textContent = "Loading translation...";

  const response = await fetch(`/api/laws/${lawId}/translate?target=${encodeURIComponent(targetLanguage)}`);
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    box.innerHTML = `
      <h4>${escapeHtml(payload.message || "Translation unavailable")}</h4>
      ${payload.fallbackUrl ? `<p><a href="${escapeAttribute(payload.fallbackUrl)}" target="_blank" rel="noreferrer">Open in Google Translate</a></p>` : ""}
    `;
    return;
  }

  box.innerHTML = `
    <h4>${escapeHtml(payload.translatedTitle || "")}</h4>
    <p>${escapeHtml(payload.translatedContent || "")}</p>
  `;
};

const appendChatMessage = (text, role, citations = []) => {
  const message = document.createElement("div");
  message.className = `chat-message ${role}`;
  message.innerHTML = `<div>${escapeHtml(text)}</div>`;

  if (citations.length) {
    const citationsEl = document.createElement("div");
    citationsEl.className = "citation-list";
    citations.slice(0, 3).forEach((citation) => {
      const card = document.createElement("div");
      card.className = "citation-card";
      card.innerHTML = `
        <strong>${escapeHtml(citation.title || "")}</strong>
        <small>${escapeHtml([citation.articleNumber, citation.documentTitle, citation.lawReference].filter(Boolean).join(" | "))}</small>
      `;
      citationsEl.append(card);
    });
    message.append(citationsEl);
  }

  elements.chatFeed.append(message);
  elements.chatFeed.scrollTop = elements.chatFeed.scrollHeight;
};

const ask = async (message) => {
  appendChatMessage(message, "user");
  appendChatMessage("Searching the indexed corpus...", "assistant");

  const response = await fetch("/api/laws/chat", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message })
  });

  if (!response.ok) {
    throw new Error("Chat failed");
  }

  const payload = await response.json();
  elements.chatFeed.lastElementChild?.remove();
  appendChatMessage(payload.answer || "", "assistant", payload.citations || []);
};

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

const escapeAttribute = escapeHtml;

fetch("/api/laws/overview")
  .then((response) => response.json())
  .then(renderOverview)
  .catch(() => {
    elements.categoryList.textContent = "Corpus overview unavailable until the database is connected.";
  });

elements.searchForm.addEventListener("submit", (event) => {
  event.preventDefault();
  search(elements.searchInput.value).catch(() => {
    setSearchState("Search failed. Check the Laravel database connection and try again.");
  });
});

document.querySelectorAll("[data-query]").forEach((button) => {
  button.addEventListener("click", () => {
    search(button.dataset.query).catch(() => {
      setSearchState("Search failed. Check the Laravel database connection and try again.");
    });
  });
});

elements.resultsList.addEventListener("click", (event) => {
  const button = event.target.closest("[data-translate]");
  if (!button) {
    return;
  }

  translate(button.dataset.translate, button.dataset.target).catch(() => {
    const box = document.querySelector(`#translation-${button.dataset.translate}`);
    box.classList.remove("is-hidden");
    box.textContent = "Translation failed.";
  });
});

elements.chatForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const message = elements.chatInput.value.trim();

  if (!message) {
    return;
  }

  elements.chatInput.value = "";
  ask(message).catch(() => {
    appendChatMessage("The assistant could not search the indexed corpus right now. Check the Laravel API and database connection.", "assistant");
  });
});
