const form = document.getElementById("search-form");
const input = document.getElementById("search-input");
const statusMessage = document.getElementById("status-message");
const resultsList = document.getElementById("results-list");
const resultsTitle = document.getElementById("results-title");
const resultsMeta = document.getElementById("results-meta");
const quickSearchButtons = document.querySelectorAll("[data-suggestion]");
const overviewStats = document.getElementById("overview-stats");
const categoryHighlights = document.getElementById("category-highlights");
<<<<<<< HEAD
const suggestionsPanel = document.getElementById("suggestions-panel");
const suggestionsList = document.getElementById("suggestions-list");
const clearSearchButton = document.getElementById("clear-search");
const resultsToolbar = document.getElementById("results-toolbar");
let activeSearchController = null;
let searchDebounceTimer = null;
let activeSuggestionController = null;
let suggestionDebounceTimer = null;
let currentSuggestions = [];
let highlightedSuggestionIndex = -1;
=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c

const languageLabels = {
  fr: "French",
  en: "English",
  ar: "Arabic"
};

const escapeHtml = (value) =>
  String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");

const normalizeTags = (tags) => {
  if (Array.isArray(tags)) {
    return tags;
  }

  if (typeof tags === "string") {
    try {
      const parsedTags = JSON.parse(tags);
      return Array.isArray(parsedTags) ? parsedTags : [];
    } catch (error) {
      return [];
    }
  }

  return [];
};

const getLanguageLabel = (languageCode) =>
  languageLabels[languageCode] || String(languageCode || "original").toUpperCase();

const formatCategoryLabel = (category) =>
  String(category || "general")
    .split("-")
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");

const formatCount = (value) => new Intl.NumberFormat("en-US").format(Number(value || 0));
<<<<<<< HEAD
const SEARCH_ICON = "⌕";
=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c

const renderOverview = (overview) => {
  if (!overviewStats || !categoryHighlights) {
    return;
  }

  const statValues = overviewStats.querySelectorAll(".overview-value");

  if (statValues.length >= 3) {
    statValues[0].textContent = formatCount(overview.totalArticles);
    statValues[1].textContent = formatCount(overview.totalDocuments);
    statValues[2].textContent = formatCount(overview.totalCategories);
  }

  categoryHighlights.innerHTML = (overview.categories || [])
    .slice(0, 6)
    .map(
      (item) => `
        <div class="highlight-pill">
          <span class="highlight-name">${escapeHtml(formatCategoryLabel(item.category))}</span>
          <span class="highlight-count">${escapeHtml(formatCount(item.articleCount))} articles</span>
        </div>
      `
    )
    .join("");
};

const loadOverview = async () => {
  try {
    const response = await fetch("/api/laws/overview");

    if (!response.ok) {
      throw new Error("Overview request failed");
    }

    const overview = await response.json();
    renderOverview(overview);
  } catch (error) {
    if (overviewStats) {
      overviewStats.classList.add("overview-stats-muted");
    }
  }
};

const buildSourceMarkup = (law) => {
  const sourceParts = [law.document_title, law.law_reference, law.source_name].filter(Boolean);

  if (sourceParts.length === 0 && !law.source_url) {
    return "";
  }

  return `
    <div class="source-block">
      <div class="source-label">Source</div>
      ${sourceParts.length ? `<div class="source-meta">${escapeHtml(sourceParts.join(" | "))}</div>` : ""}
      ${
        law.source_url
          ? `<a class="source-link" href="${escapeHtml(law.source_url)}" target="_blank" rel="noreferrer">Open official source document</a>`
          : ""
      }
    </div>
  `;
};

const renderResults = (payload) => {
<<<<<<< HEAD
  const { query, count, results, hasMore, limit } = payload;
=======
  const { query, count, results } = payload;
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c

  resultsTitle.textContent = query
    ? `Results for "${query}"`
    : "Search the Moroccan law library";
<<<<<<< HEAD
  resultsMeta.textContent = hasMore
    ? `Showing top ${limit} results`
    : `${count} result${count === 1 ? "" : "s"}`;

  if (!count) {
    if (resultsToolbar) {
      resultsToolbar.hidden = true;
    }
=======
  resultsMeta.textContent = `${count} result${count === 1 ? "" : "s"}`;

  if (!count) {
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
    statusMessage.hidden = false;
    statusMessage.textContent = query
      ? `No laws matched "${query}". Try a broader keyword.`
      : "Enter a keyword to begin searching.";
    resultsList.innerHTML = "";
    return;
  }

  statusMessage.hidden = true;
<<<<<<< HEAD
  if (resultsToolbar) {
    resultsToolbar.hidden = false;
  }
=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  resultsList.innerHTML = results
    .map((law) => {
      const tags = normalizeTags(law.tags);
      const tagMarkup = tags
        .map((tag) => `<span class="tag">${escapeHtml(tag)}</span>`)
        .join("");

      return `
        <article class="result-card">
          <div class="result-category">${escapeHtml(formatCategoryLabel(law.category))}</div>
          <h3>${escapeHtml(law.title)}</h3>
          <div class="article-pill">${escapeHtml(law.article_number)}</div>
          <div class="result-meta">Original language: ${escapeHtml(getLanguageLabel(law.language))}</div>
          <p>${escapeHtml(law.content)}</p>
          ${tagMarkup ? `<div class="tag-list">${tagMarkup}</div>` : ""}
          ${buildSourceMarkup(law)}
          <div class="card-actions">
            <button class="translate-button" type="button" data-law-id="${escapeHtml(law.id)}" data-target="en">
              Translate to English
            </button>
            <button class="translate-button" type="button" data-law-id="${escapeHtml(law.id)}" data-target="ar">
              Translate to Arabic
            </button>
            <span class="translate-status" data-translate-status></span>
          </div>
          <section class="translation-panel" data-translation-panel hidden></section>
          <div class="relevance">Ranking score: ${escapeHtml(law.relevance_score ?? 0)}</div>
        </article>
      `;
    })
    .join("");
};

<<<<<<< HEAD
const hideSuggestions = () => {
  currentSuggestions = [];
  highlightedSuggestionIndex = -1;

  if (suggestionsPanel) {
    suggestionsPanel.hidden = true;
  }

  if (suggestionsList) {
    suggestionsList.innerHTML = "";
  }

  input.setAttribute("aria-expanded", "false");
};

const applySuggestion = (suggestionText) => {
  input.value = suggestionText;
  hideSuggestions();
  runSearch(suggestionText);
};

const renderSuggestions = (suggestions) => {
  currentSuggestions = suggestions;
  highlightedSuggestionIndex = -1;

  if (!suggestionsPanel || !suggestionsList) {
    return;
  }

  if (suggestions.length === 0) {
    hideSuggestions();
    return;
  }

  suggestionsPanel.hidden = false;
  input.setAttribute("aria-expanded", "true");
  suggestionsList.innerHTML = suggestions
    .map(
      (suggestion, index) => `
        <button
          class="suggestion-item"
          type="button"
          role="option"
          data-suggestion-index="${index}"
          aria-selected="false"
        >
          <span class="suggestion-main">
            <span class="suggestion-icon">${SEARCH_ICON}</span>
            <span>${escapeHtml(suggestion.text)}</span>
          </span>
          <span class="suggestion-type">${escapeHtml(suggestion.type)}</span>
        </button>
      `
    )
    .join("");
};

const highlightSuggestion = (nextIndex) => {
  const items = suggestionsList ? suggestionsList.querySelectorAll(".suggestion-item") : [];

  if (!items.length) {
    highlightedSuggestionIndex = -1;
    return;
  }

  highlightedSuggestionIndex = nextIndex;
  items.forEach((item, index) => {
    const isActive = index === highlightedSuggestionIndex;
    item.classList.toggle("is-active", isActive);
    item.setAttribute("aria-selected", String(isActive));

    if (isActive) {
      item.scrollIntoView({
        block: "nearest"
      });
    }
  });
};

const loadSuggestions = async (query) => {
  const normalizedQuery = query.trim();

  if (normalizedQuery.length < 2) {
    hideSuggestions();
    return;
  }

  if (activeSuggestionController) {
    activeSuggestionController.abort();
  }

  activeSuggestionController = new AbortController();

  try {
    const response = await fetch(
      `/api/laws/suggestions?q=${encodeURIComponent(normalizedQuery)}`,
      {
        signal: activeSuggestionController.signal
      }
    );

    if (!response.ok) {
      throw new Error("Suggestions request failed");
    }

    const payload = await response.json();
    renderSuggestions(payload.suggestions || []);
  } catch (error) {
    if (error.name !== "AbortError") {
      hideSuggestions();
    }
  } finally {
    activeSuggestionController = null;
  }
};

=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
const setLoadingState = (isLoading) => {
  statusMessage.hidden = false;
  statusMessage.textContent = isLoading ? "Searching laws..." : statusMessage.textContent;
};

const searchLaws = async (query) => {
  setLoadingState(true);
  resultsList.innerHTML = "";
<<<<<<< HEAD
  hideSuggestions();

  if (activeSearchController) {
    activeSearchController.abort();
  }

  activeSearchController = new AbortController();

  try {
    const response = await fetch(`/api/laws/search?q=${encodeURIComponent(query)}`, {
      signal: activeSearchController.signal
    });
=======

  try {
    const response = await fetch(`/api/laws/search?q=${encodeURIComponent(query)}`);
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c

    if (!response.ok) {
      throw new Error("Search request failed");
    }

    const payload = await response.json();
    renderResults(payload);
  } catch (error) {
<<<<<<< HEAD
    if (error.name === "AbortError") {
      return;
    }

=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
    statusMessage.hidden = false;
    statusMessage.textContent = "Search failed. Please try again.";
    resultsTitle.textContent = "Search unavailable";
    resultsMeta.textContent = "";
<<<<<<< HEAD
  } finally {
    activeSearchController = null;
=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  }
};

const runSearch = (query) => {
  const normalizedQuery = query.trim();

  if (!normalizedQuery) {
<<<<<<< HEAD
    hideSuggestions();
    renderResults({
      query: "",
      count: 0,
      results: [],
      hasMore: false,
      limit: 0
=======
    renderResults({
      query: "",
      count: 0,
      results: []
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
    });
    return;
  }

  input.value = normalizedQuery;
<<<<<<< HEAD
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    searchLaws(normalizedQuery);
  }, 180);
=======
  searchLaws(normalizedQuery);
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
};

const getTranslateButtonText = (targetLanguage) =>
  `Translate to ${getLanguageLabel(targetLanguage)}`;

const getHideTranslationButtonText = (targetLanguage) =>
  `Hide ${getLanguageLabel(targetLanguage)} translation`;

const renderTranslation = (panel, translation) => {
  const isArabic = translation.targetLanguage === "ar";

  panel.classList.toggle("is-rtl", isArabic);
  panel.setAttribute("dir", isArabic ? "rtl" : "ltr");
  panel.innerHTML = `
    <div class="translation-label">${escapeHtml(getLanguageLabel(translation.targetLanguage))} translation</div>
    <h4>${escapeHtml(translation.translatedTitle)}</h4>
    <p>${escapeHtml(translation.translatedContent)}</p>
  `;
};

resultsList.addEventListener("click", async (event) => {
  const button = event.target.closest(".translate-button");

  if (!button) {
    return;
  }

  const card = button.closest(".result-card");
  const panel = card.querySelector("[data-translation-panel]");
  const status = card.querySelector("[data-translate-status]");
  const buttons = card.querySelectorAll(".translate-button");
  const lawId = button.dataset.lawId;
  const targetLanguage = button.dataset.target || "en";
  const targetLabel = getLanguageLabel(targetLanguage);

  if (panel.dataset.loaded === "true" && panel.dataset.targetLanguage === targetLanguage) {
    const willShow = panel.hidden;
    panel.hidden = !willShow;
    button.textContent = willShow
      ? getHideTranslationButtonText(targetLanguage)
      : getTranslateButtonText(targetLanguage);
    status.textContent = willShow ? `${targetLabel} translation loaded.` : "";
    return;
  }

  buttons.forEach((translationButton) => {
    translationButton.disabled = true;
  });
  button.textContent = "Translating...";
  status.textContent = `Fetching ${targetLabel} translation...`;

  try {
    const response = await fetch(
      `/api/laws/${encodeURIComponent(lawId)}/translate?target=${encodeURIComponent(targetLanguage)}`
    );

    if (!response.ok) {
      const errorPayload = await response.json().catch(() => null);
      const error = new Error(
        errorPayload?.message || "Translation request failed"
      );
      error.fallbackUrl = errorPayload?.fallbackUrl || "";
      throw error;
    }

    const translation = await response.json();
    renderTranslation(panel, translation);
    panel.hidden = false;
    panel.dataset.loaded = "true";
    panel.dataset.targetLanguage = targetLanguage;
    buttons.forEach((translationButton) => {
      translationButton.textContent = getTranslateButtonText(translationButton.dataset.target || "en");
    });
    button.textContent = getHideTranslationButtonText(targetLanguage);
    status.textContent = `Translated from ${getLanguageLabel(translation.sourceLanguage)} to ${getLanguageLabel(translation.targetLanguage)}.`;
  } catch (error) {
    status.innerHTML = error.fallbackUrl
      ? `Inline translation is unavailable here. <a class="source-link" href="${escapeHtml(
          error.fallbackUrl
        )}" target="_blank" rel="noreferrer">Open ${targetLabel} translation in Google Translate</a>`
      : "Translation is temporarily unavailable.";
    button.textContent = getTranslateButtonText(targetLanguage);
  } finally {
    buttons.forEach((translationButton) => {
      translationButton.disabled = false;
    });
  }
});

form.addEventListener("submit", (event) => {
  event.preventDefault();
  runSearch(input.value);
});

<<<<<<< HEAD
input.addEventListener("input", () => {
  const currentValue = input.value.trim();

  if (clearSearchButton) {
    clearSearchButton.hidden = currentValue.length === 0;
  }

  clearTimeout(suggestionDebounceTimer);
  suggestionDebounceTimer = setTimeout(() => {
    loadSuggestions(currentValue);
  }, 120);
});

input.addEventListener("keydown", (event) => {
  if (!currentSuggestions.length) {
    return;
  }

  if (event.key === "ArrowDown") {
    event.preventDefault();
    const nextIndex =
      highlightedSuggestionIndex >= currentSuggestions.length - 1
        ? 0
        : highlightedSuggestionIndex + 1;
    highlightSuggestion(nextIndex);
    return;
  }

  if (event.key === "ArrowUp") {
    event.preventDefault();
    const nextIndex =
      highlightedSuggestionIndex <= 0
        ? currentSuggestions.length - 1
        : highlightedSuggestionIndex - 1;
    highlightSuggestion(nextIndex);
    return;
  }

  if (event.key === "Enter" && highlightedSuggestionIndex >= 0) {
    event.preventDefault();
    applySuggestion(currentSuggestions[highlightedSuggestionIndex].text);
    return;
  }

  if (event.key === "Escape") {
    hideSuggestions();
  }
});

input.addEventListener("focus", () => {
  const currentValue = input.value.trim();

  if (currentValue.length >= 2 && currentSuggestions.length > 0) {
    suggestionsPanel.hidden = false;
    input.setAttribute("aria-expanded", "true");
  }
});

document.addEventListener("click", (event) => {
  if (!event.target.closest(".search-panel")) {
    hideSuggestions();
  }
});

if (suggestionsList) {
  suggestionsList.addEventListener("click", (event) => {
    const suggestionButton = event.target.closest("[data-suggestion-index]");

    if (!suggestionButton) {
      return;
    }

    const suggestion = currentSuggestions[Number(suggestionButton.dataset.suggestionIndex)];

    if (suggestion) {
      applySuggestion(suggestion.text);
    }
  });
}

if (clearSearchButton) {
  clearSearchButton.addEventListener("click", () => {
    input.value = "";
    clearSearchButton.hidden = true;
    hideSuggestions();
    input.focus();
    renderResults({
      query: "",
      count: 0,
      results: [],
      hasMore: false,
      limit: 0
    });
  });
}

=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
quickSearchButtons.forEach((button) => {
  button.addEventListener("click", () => {
    runSearch(button.dataset.suggestion || "");
  });
});

loadOverview();
