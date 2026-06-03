import React, { FormEvent, KeyboardEvent, useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import {
  ArrowRight,
  BookOpen,
  Bot,
  Building2,
  CheckCircle2,
  CreditCard,
  Database,
  ExternalLink,
  FileText,
  Globe2,
  Languages,
  Layers3,
  LogIn,
  Loader2,
  Send,
  Search,
  ShieldCheck,
  Sparkles,
  X
} from "lucide-react";
import "./styles.css";

type OverviewCategory = {
  category: string;
  articleCount: number;
  documentCount: number;
};

type Overview = {
  totalArticles: number;
  totalDocuments: number;
  totalCategories: number;
  categories: OverviewCategory[];
};

type Law = {
  id: number;
  title: string;
  article_number: string;
  content: string;
  tags: string[] | string | null;
  document_title?: string;
  law_reference?: string;
  category?: string;
  source_name?: string;
  source_url?: string;
  language?: string;
  relevance_score?: number;
};

type SearchPayload = {
  query: string;
  count: number;
  results: Law[];
  hasMore: boolean;
  limit: number;
};

type Suggestion = {
  text: string;
  type: string;
};

type Translation = {
  sourceLanguage: string;
  targetLanguage: string;
  translatedTitle: string;
  translatedContent: string;
};

type TranslationState = {
  loading?: "en" | "ar";
  active?: "en" | "ar";
  data?: Translation;
  status?: string;
  fallbackUrl?: string;
};

type ChatCitation = {
  id: number;
  title: string;
  articleNumber: string;
  content: string;
  documentTitle?: string;
  lawReference?: string;
  sourceName?: string;
  sourceUrl?: string;
  category?: string;
  relevanceScore?: number;
  matchedQuery?: string;
};

type ChatMessage = {
  role: "assistant" | "user";
  text: string;
  citations?: ChatCitation[];
};

const quickSearches = ["immobilier", "commerce", "travail", "famille", "fiscalite", "banque", "contrats", "propriete"];

const categoryLabels: Record<string, string> = {
  "administrative-governance": "Gouvernance administrative",
  agriculture: "Agriculture",
  aquaculture: "Aquaculture",
  archives: "Archives",
  aviation: "Aviation",
  banking: "Banque",
  civil: "Droit civil",
  "civil-procedure": "Procedure civile",
  commercial: "Droit commercial",
  consumer: "Consommation",
  criminal: "Droit penal",
  culture: "Culture",
  "disability-rights": "Droits des personnes handicapees",
  education: "Education",
  "electronic-transactions": "Transactions electroniques",
  energy: "Energie",
  "energy-efficiency": "Efficacite energetique",
  environment: "Environnement",
  family: "Famille",
  "financial-market": "Marches financiers",
  fintech: "Fintech",
  fisheries: "Peche",
  "foreign-trade": "Commerce exterieur",
  health: "Sante",
  hydrocarbons: "Hydrocarbures",
  "industrial-safety": "Securite industrielle",
  insurance: "Assurances",
  investment: "Investissement",
  "judicial-organization": "Organisation judiciaire",
  "judicial-professions": "Professions judiciaires",
  labor: "Travail",
  "local-taxation": "Fiscalite locale",
  "market-regulation": "Regulation du marche",
  microfinance: "Microfinance",
  "nuclear-safety": "Surete nucleaire",
  ports: "Ports",
  prisons: "Etablissements penitentiaires",
  "public-enterprises": "Entreprises publiques",
  "public-finance": "Finances publiques",
  "public-procurement": "Commande publique",
  "real-estate": "Immobilier",
  "regulated-professions": "Professions reglementees",
  "rights-liberties": "Droits et libertes",
  security: "Securite",
  "social-protection": "Protection sociale",
  "sports-events": "Evenements sportifs",
  tax: "Fiscalite",
  "territorial-governance": "Collectivites territoriales",
  tourism: "Tourisme",
  veterinary: "Veterinaire"
};

const categorySearchTerms: Record<string, string> = {
  "administrative-governance": "administration",
  banking: "banque",
  civil: "droit civil",
  "civil-procedure": "procedure civile",
  commercial: "commerce",
  consumer: "consommation",
  criminal: "droit penal",
  "disability-rights": "handicap",
  education: "education",
  "electronic-transactions": "transactions electroniques",
  energy: "energie",
  "energy-efficiency": "efficacite energetique",
  environment: "environnement",
  family: "famille",
  "financial-market": "marches financiers",
  fisheries: "peche",
  "foreign-trade": "commerce exterieur",
  health: "sante",
  hydrocarbons: "hydrocarbures",
  "industrial-safety": "securite industrielle",
  insurance: "assurance",
  investment: "investissement",
  "judicial-organization": "organisation judiciaire",
  "judicial-professions": "professions judiciaires",
  labor: "travail",
  "local-taxation": "fiscalite locale",
  "market-regulation": "regulation du marche",
  "nuclear-safety": "surete nucleaire",
  ports: "ports",
  prisons: "etablissements penitentiaires",
  "public-enterprises": "entreprises publiques",
  "public-finance": "finances publiques",
  "public-procurement": "commande publique",
  "real-estate": "immobilier",
  "regulated-professions": "professions reglementees",
  "rights-liberties": "droits et libertes",
  security: "securite",
  "social-protection": "protection sociale",
  "sports-events": "evenements sportifs",
  tax: "fiscalite",
  "territorial-governance": "collectivites territoriales",
  veterinary: "veterinaire"
};

const languageLabels: Record<string, string> = {
  ar: "Arabic",
  en: "English",
  fr: "French"
};

const formatCount = (value: number | undefined) =>
  new Intl.NumberFormat("en-US").format(Number(value || 0));

const formatCategory = (category?: string) =>
  categoryLabels[category || ""] ||
  (category || "general")
    .split("-")
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");

const getCategorySearchTerm = (category?: string) =>
  categorySearchTerms[category || ""] || formatCategory(category).toLowerCase();

const normalizeTags = (tags: Law["tags"]) => {
  if (Array.isArray(tags)) {
    return tags;
  }

  if (typeof tags === "string") {
    try {
      const parsed = JSON.parse(tags) as unknown;
      return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch {
      return [];
    }
  }

  return [];
};

const getLanguageLabel = (languageCode?: string) =>
  languageLabels[languageCode || ""] || String(languageCode || "original").toUpperCase();

function App() {
  const [overview, setOverview] = useState<Overview | null>(null);
  const [query, setQuery] = useState("");
  const [submittedQuery, setSubmittedQuery] = useState("");
  const [searchPayload, setSearchPayload] = useState<SearchPayload | null>(null);
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [highlightedSuggestionIndex, setHighlightedSuggestionIndex] = useState(-1);
  const [isSearching, setIsSearching] = useState(false);
  const [searchError, setSearchError] = useState("");
  const [activeResultCategory, setActiveResultCategory] = useState("");
  const [translationStates, setTranslationStates] = useState<Record<number, TranslationState>>({});
  const [chatInput, setChatInput] = useState("");
  const [chatMessages, setChatMessages] = useState<ChatMessage[]>([
    {
      role: "assistant",
      text: "Hi. Tell me what you want to look up. You can ask normally, like laws about real estate, labor termination, company rules, or a specific article."
    }
  ]);
  const [isChatLoading, setIsChatLoading] = useState(false);
  const [chatError, setChatError] = useState("");
  const [isSupportOpen, setIsSupportOpen] = useState(false);
  const [isWorkspaceOpen, setIsWorkspaceOpen] = useState(false);
  const [isLoginOpen, setIsLoginOpen] = useState(false);
  const searchControllerRef = useRef<AbortController | null>(null);
  const suggestionControllerRef = useRef<AbortController | null>(null);
  const resultsRef = useRef<HTMLElement | null>(null);
  const chatFeedRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    let isMounted = true;

    fetch("/api/laws/overview")
      .then((response) => {
        if (!response.ok) {
          throw new Error("Overview request failed");
        }

        return response.json() as Promise<Overview>;
      })
      .then((data) => {
        if (isMounted) {
          setOverview(data);
        }
      })
      .catch(() => {
        if (isMounted) {
          setOverview(null);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    const normalizedQuery = query.trim();

    if (normalizedQuery.length < 2) {
      setSuggestions([]);
      return;
    }

    const timer = window.setTimeout(() => {
      suggestionControllerRef.current?.abort();
      const controller = new AbortController();
      suggestionControllerRef.current = controller;

      fetch(`/api/laws/suggestions?q=${encodeURIComponent(normalizedQuery)}`, {
        signal: controller.signal
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Suggestions request failed");
          }

          return response.json() as Promise<{ suggestions: Suggestion[] }>;
        })
        .then((payload) => {
          setSuggestions(payload.suggestions || []);
          setHighlightedSuggestionIndex(-1);
        })
        .catch((error: Error) => {
          if (error.name !== "AbortError") {
            setSuggestions([]);
          }
        });
    }, 120);

    return () => {
      window.clearTimeout(timer);
    };
  }, [query]);

  useEffect(() => {
    if (isSupportOpen) {
      chatFeedRef.current?.scrollTo({
        top: chatFeedRef.current.scrollHeight,
        behavior: "smooth"
      });
    }
  }, [chatMessages, isChatLoading, isSupportOpen]);

  const runSearch = async (nextQuery = query, displayQuery = nextQuery, nextCategory = "") => {
    const normalizedQuery = nextQuery.trim();
    const normalizedDisplayQuery = displayQuery.trim() || normalizedQuery;

    if (!normalizedQuery) {
      setSearchPayload(null);
      setSearchError("");
      setSubmittedQuery("");
      return;
    }

    searchControllerRef.current?.abort();
    const controller = new AbortController();
    searchControllerRef.current = controller;
    setQuery(normalizedQuery);
    setSubmittedQuery(normalizedDisplayQuery);
    setSuggestions([]);
    setSearchError("");
    setActiveResultCategory(nextCategory);
    setIsSearching(true);

    try {
      const response = await fetch(`/api/laws/search?q=${encodeURIComponent(normalizedQuery)}`, {
        signal: controller.signal
      });

      if (!response.ok) {
        throw new Error("Search request failed");
      }

      const payload = (await response.json()) as SearchPayload;
      setSearchPayload(payload);
      window.setTimeout(() => {
        resultsRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 80);
    } catch (error) {
      if ((error as Error).name !== "AbortError") {
        setSearchError("Search failed. Please try again.");
        setSearchPayload(null);
      }
    } finally {
      setIsSearching(false);
    }
  };

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    void runSearch();
  };

  const applySuggestion = (suggestionText: string) => {
    setQuery(suggestionText);
    setSuggestions([]);
    void runSearch(suggestionText);
  };

  const handleInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (!suggestions.length) {
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setHighlightedSuggestionIndex((current) => (current >= suggestions.length - 1 ? 0 : current + 1));
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      setHighlightedSuggestionIndex((current) => (current <= 0 ? suggestions.length - 1 : current - 1));
    }

    if (event.key === "Enter" && highlightedSuggestionIndex >= 0) {
      event.preventDefault();
      applySuggestion(suggestions[highlightedSuggestionIndex].text);
    }

    if (event.key === "Escape") {
      setSuggestions([]);
    }
  };

  const translateLaw = async (law: Law, targetLanguage: "en" | "ar") => {
    const current = translationStates[law.id];

    if (current?.data?.targetLanguage === targetLanguage && current.active === targetLanguage) {
      setTranslationStates((states) => ({
        ...states,
        [law.id]: {
          ...current,
          active: undefined,
          status: ""
        }
      }));
      return;
    }

    if (current?.data?.targetLanguage === targetLanguage) {
      setTranslationStates((states) => ({
        ...states,
        [law.id]: {
          ...current,
          active: targetLanguage,
          status: `${getLanguageLabel(targetLanguage)} translation loaded.`
        }
      }));
      return;
    }

    setTranslationStates((states) => ({
      ...states,
      [law.id]: {
        loading: targetLanguage,
        status: `Fetching ${getLanguageLabel(targetLanguage)} translation...`
      }
    }));

    try {
      const response = await fetch(`/api/laws/${law.id}/translate?target=${targetLanguage}`);

      if (!response.ok) {
        const errorPayload = (await response.json().catch(() => null)) as { message?: string; fallbackUrl?: string } | null;
        throw new Error(JSON.stringify(errorPayload || { message: "Translation failed" }));
      }

      const translation = (await response.json()) as Translation;
      setTranslationStates((states) => ({
        ...states,
        [law.id]: {
          active: targetLanguage,
          data: translation,
          status: `Translated from ${getLanguageLabel(translation.sourceLanguage)} to ${getLanguageLabel(
            translation.targetLanguage
          )}.`
        }
      }));
    } catch (error) {
      let fallbackUrl = "";
      try {
        fallbackUrl = JSON.parse((error as Error).message).fallbackUrl || "";
      } catch {
        fallbackUrl = "";
      }

      setTranslationStates((states) => ({
        ...states,
        [law.id]: {
          fallbackUrl,
          status: "Inline translation is temporarily unavailable."
        }
      }));
    }
  };

  const askAssistant = async (event?: FormEvent) => {
    event?.preventDefault();
    const normalizedMessage = chatInput.trim();

    if (!normalizedMessage || isChatLoading) {
      return;
    }

    const requestHistory = chatMessages.slice(-8).map((message) => ({
      role: message.role,
      text: message.text
    }));

    setChatInput("");
    setChatError("");
    setIsChatLoading(true);
    setChatMessages((messages) => [...messages, { role: "user", text: normalizedMessage }]);

    try {
      const response = await fetch("/api/laws/chat", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({ message: normalizedMessage, history: requestHistory })
      });

      if (!response.ok) {
        throw new Error("Chat request failed");
      }

      const payload = (await response.json()) as {
        answer: string;
        citations: ChatCitation[];
      };

      setChatMessages((messages) => [
        ...messages,
        {
          role: "assistant",
          text: payload.answer,
          citations: payload.citations || []
        }
      ]);
    } catch {
      setChatError("The assistant could not search the database right now. Please try again.");
    } finally {
      setIsChatLoading(false);
    }
  };

  const allResults = searchPayload?.results || [];
  const resultCategories = Array.from(
    allResults.reduce((categoryMap, law) => {
      const category = law.category || "general";
      categoryMap.set(category, (categoryMap.get(category) || 0) + 1);
      return categoryMap;
    }, new Map<string, number>())
  ).sort(([, leftCount], [, rightCount]) => rightCount - leftCount);
  const results = activeResultCategory
    ? allResults.filter((law) => (law.category || "general") === activeResultCategory)
    : allResults;
  const visibleCategories = overview?.categories || [];

  if (!isWorkspaceOpen) {
    return (
      <LandingPage
        overview={overview}
        isLoginOpen={isLoginOpen}
        onOpenLogin={() => setIsLoginOpen(true)}
        onCloseLogin={() => setIsLoginOpen(false)}
        onEnterWorkspace={() => {
          setIsLoginOpen(false);
          setIsWorkspaceOpen(true);
        }}
      />
    );
  }

  return (
    <main className="app-shell">
      <header className="topbar">
        <a className="brand-lockup" href="/" aria-label="Marokko Biz home">
          <img src="/marokko-biz-icon.png" alt="Marokko Biz" />
          <span>Marokko Biz</span>
        </a>
        <div className="topbar-meta" aria-label="Library status">
          <button className="topbar-link" type="button" onClick={() => setIsWorkspaceOpen(false)}>
            Landing
          </button>
          <span>
            <span className="live-dot" />
            Live library
          </span>
          <strong>{formatCount(overview?.totalArticles)} articles</strong>
        </div>
      </header>

      <section className="command-center">
        <div className="search-hero">
          <div className="hero-kicker">
            <Sparkles size={16} />
            Official Moroccan law search
          </div>
          <h1>Moroccan law, instantly searchable.</h1>
          <p className="hero-subcopy">
            Find official articles, verify sources, and translate results from one calm workspace.
          </p>

          <form className="search-console" onSubmit={handleSubmit}>
            <div className="search-input-row">
              <Search className="search-input-icon" size={22} />
              <input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                onKeyDown={handleInputKeyDown}
                placeholder="Search articles, codes, sources, categories..."
                aria-autocomplete="list"
                aria-expanded={suggestions.length > 0}
              />
              {query ? (
                <button
                  className="icon-button"
                  type="button"
                  onClick={() => {
                    setQuery("");
                    setSuggestions([]);
                  }}
                  aria-label="Clear search"
                >
                  <X size={18} />
                </button>
              ) : null}
              <button className="search-button" type="submit" disabled={isSearching}>
                {isSearching ? <Loader2 className="spin" size={20} /> : <ArrowRight size={20} />}
                Search
              </button>
            </div>

            {suggestions.length > 0 ? (
              <div className="suggestions-panel" role="listbox">
                {suggestions.map((suggestion, index) => (
                  <button
                    className={`suggestion-row ${index === highlightedSuggestionIndex ? "is-active" : ""}`}
                    type="button"
                    key={`${suggestion.type}-${suggestion.text}`}
                    onClick={() => applySuggestion(suggestion.text)}
                  >
                    <span>
                      <Search size={15} />
                      {suggestion.text}
                    </span>
                    <strong>{suggestion.type}</strong>
                  </button>
                ))}
              </div>
            ) : null}
          </form>

          <div className="quick-strip">
            {quickSearches.map((item) => (
              <button type="button" key={item} onClick={() => void runSearch(item)}>
                {item}
              </button>
            ))}
          </div>

          <div className="signal-row" aria-label="Search capabilities">
            <span>
              <ShieldCheck size={16} />
              Official sources
            </span>
            <span>
              <Layers3 size={16} />
              Article ranking
            </span>
            <span>
              <Languages size={16} />
              EN / AR tools
            </span>
          </div>
        </div>

        <aside className="library-panel">
          <div className="library-heading">
            <span>Index</span>
            <strong>Coverage snapshot</strong>
          </div>
          <div className="stat-grid">
            <Metric icon={<BookOpen size={18} />} label="Articles" value={formatCount(overview?.totalArticles)} />
            <Metric icon={<FileText size={18} />} label="Sources" value={formatCount(overview?.totalDocuments)} />
            <Metric icon={<Globe2 size={18} />} label="Areas" value={formatCount(overview?.totalCategories)} />
          </div>

          <div className="category-radar">
            <div className="panel-title">
              <ShieldCheck size={17} />
              Coverage radar
            </div>
            <div className="coverage-list">
              {visibleCategories.map((category) => (
                <button
                  type="button"
                  className="coverage-item"
                  key={category.category}
                  onClick={() => void runSearch(category.category, formatCategory(category.category))}
                >
                  <span>{formatCategory(category.category)}</span>
                  <strong>{formatCount(category.articleCount)}</strong>
                </button>
              ))}
            </div>
          </div>
          <div className="library-footer">
            <Database size={18} />
            <span>Connected to the local Moroccan law database</span>
          </div>
        </aside>
      </section>

      <section className="results-zone" ref={resultsRef}>
        <div className="results-head">
          <div>
            <span className="section-label">Article results</span>
            <h2>{submittedQuery ? `Results for "${submittedQuery}"` : "Ready when you are"}</h2>
          </div>
          <div className="result-count">
            {searchPayload && activeResultCategory
              ? `${results.length} visible`
              : searchPayload
              ? searchPayload.hasMore
                ? `Top ${searchPayload.limit}`
                : `${searchPayload.count} result${searchPayload.count === 1 ? "" : "s"}`
              : "No search yet"}
          </div>
        </div>

        {allResults.length ? (
          <div className="result-tools" aria-label="Result filters">
            <div>
              <strong>{formatCount(allResults.length)}</strong>
              <span>{searchPayload?.hasMore ? "top matches" : "matches"}</span>
            </div>
            <div className="result-filter-strip">
              <button
                type="button"
                className={!activeResultCategory ? "is-active" : ""}
                onClick={() => setActiveResultCategory("")}
              >
                All
              </button>
              {resultCategories.map(([category, count]) => (
                <button
                  type="button"
                  className={activeResultCategory === category ? "is-active" : ""}
                  key={category}
                  onClick={() => setActiveResultCategory(category)}
                >
                  {formatCategory(category)}
                  <span>{formatCount(count)}</span>
                </button>
              ))}
            </div>
          </div>
        ) : null}

        {isSearching ? (
          <div className="state-panel">
            <Loader2 className="spin" size={24} />
            Searching official articles...
          </div>
        ) : null}

        {searchError ? <div className="state-panel is-error">{searchError}</div> : null}

        {!isSearching && !searchError && !results.length ? (
          <div className="empty-panel">
            <Building2 size={28} />
            <h3>{submittedQuery ? "No matching articles yet" : "Start with a keyword"}</h3>
            <p>
              {submittedQuery
                ? "Try a broader legal term or search by law family."
                : "Search by topic, code, reference, source, or legal area."}
            </p>
          </div>
        ) : null}

        <div className="results-list">
          {results.map((law, index) => (
            <ResultCard
              key={law.id}
              law={law}
              index={index}
              translationState={translationStates[law.id]}
              onTranslate={translateLaw}
            />
          ))}
        </div>
      </section>

      <aside className={`support-widget ${isSupportOpen ? "is-open" : ""}`} aria-label="Legal assistant chat">
        {isSupportOpen ? (
          <section className="support-panel is-chat">
            <div className="support-chat-head">
              <div className="support-team-mark">
                <span>
                  <Bot size={24} />
                </span>
                <div>
                  <strong>Marokko Biz Assistant</strong>
                  <small>Legal case chat</small>
                </div>
              </div>
              <button type="button" onClick={() => setIsSupportOpen(false)} aria-label="Close chat">
                <X size={18} />
              </button>
            </div>

            <div className="chat-card support-chat-card">
              <div className="chat-feed support-chat-feed" ref={chatFeedRef}>
                {chatMessages.map((message, index) => (
                  <div className={`chat-message is-${message.role}`} key={`${message.role}-${index}`}>
                    <div className="chat-bubble">
                      {message.role === "assistant" ? (
                        <span className="assistant-avatar">
                          <Bot size={17} />
                        </span>
                      ) : null}
                      <p>{message.text}</p>
                    </div>

                    {message.citations?.length ? (
                      <div className="citation-grid">
                        {message.citations.slice(0, 3).map((citation) => (
                          <article className="citation-card" key={citation.id}>
                            <span>{formatCategory(citation.category)}</span>
                            <strong>{citation.title}</strong>
                            <small>
                              {[citation.articleNumber, citation.documentTitle, citation.lawReference]
                                .filter(Boolean)
                                .join(" | ")}
                            </small>
                            <p>{citation.content}</p>
                            <div>
                              {citation.category !== "official-bulletin" ? (
                                <button
                                  type="button"
                                  onClick={() => {
                                    setIsSupportOpen(false);
                                    void runSearch(citation.title, citation.title, citation.category || "");
                                  }}
                                >
                                  View related results
                                </button>
                              ) : null}
                              {citation.sourceUrl ? (
                                <a href={citation.sourceUrl} target="_blank" rel="noreferrer">
                                  Source
                                </a>
                              ) : null}
                            </div>
                          </article>
                        ))}
                      </div>
                    ) : null}
                  </div>
                ))}
                {isChatLoading ? (
                  <div className="chat-message is-assistant">
                    <div className="chat-bubble">
                      <span className="assistant-avatar">
                        <Loader2 className="spin" size={17} />
                      </span>
                      <p>Thinking...</p>
                    </div>
                  </div>
                ) : null}
              </div>

              {chatError ? <div className="chat-error">{chatError}</div> : null}

              <form className="chat-input-row support-chat-input" onSubmit={askAssistant}>
                <textarea
                  value={chatInput}
                  onChange={(event) => setChatInput(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === "Enter" && !event.shiftKey) {
                      event.preventDefault();
                      event.currentTarget.form?.requestSubmit();
                    }
                  }}
                  placeholder="Enter your message..."
                  rows={2}
                />
                <button type="submit" disabled={isChatLoading || !chatInput.trim()} aria-label="Send message">
                  {isChatLoading ? <Loader2 className="spin" size={18} /> : <Send size={18} />}
                </button>
              </form>
            </div>
          </section>
        ) : null}

        <button
          className="support-launcher"
          type="button"
          onClick={() => setIsSupportOpen((open) => !open)}
          aria-label="Chat with legal assistant"
          aria-expanded={isSupportOpen}
        >
          <span className="support-tooltip">Chat with us</span>
          {isSupportOpen ? <X size={22} /> : <Bot size={24} />}
        </button>
      </aside>
    </main>
  );
}

function LandingPage({
  overview,
  isLoginOpen,
  onOpenLogin,
  onCloseLogin,
  onEnterWorkspace
}: {
  overview: Overview | null;
  isLoginOpen: boolean;
  onOpenLogin: () => void;
  onCloseLogin: () => void;
  onEnterWorkspace: () => void;
}) {
  const articleCount = formatCount(overview?.totalArticles);
  const sourceCount = formatCount(overview?.totalDocuments);

  return (
    <main className="landing-shell">
      <header className="landing-nav">
        <a className="brand-lockup landing-brand" href="/" aria-label="Marokko Biz law search home">
          <img src="/marokko-biz-icon.png" alt="Marokko Biz" />
          <span>Marokko Biz</span>
        </a>
        <nav className="landing-links" aria-label="Landing navigation">
          <a href="#platform">Platform</a>
          <a href="#workflow">Workflow</a>
          <a href="#access">Access</a>
        </nav>
        <div className="landing-nav-actions">
          <button className="ghost-action" type="button" onClick={onOpenLogin}>
            <LogIn size={18} />
            Login
          </button>
          <button className="primary-action" type="button" onClick={onEnterWorkspace}>
            Open app
            <ArrowRight size={18} />
          </button>
        </div>
      </header>

      <section className="landing-hero">
        <div className="landing-copy" id="platform">
          <div className="hero-kicker">
            <Sparkles size={16} />
            Moroccan legal intelligence
          </div>
          <h1>Search Moroccan law like a real work tool.</h1>
          <p>
            A dedicated Marokko Biz law platform for official articles, source-backed answers, translations,
            and AI case analysis. Built like the FlexTimer landing structure, but translated into a legal product.
          </p>
          <div className="landing-actions">
            <button className="primary-action is-large" type="button" onClick={onEnterWorkspace}>
              Enter workspace
              <ArrowRight size={20} />
            </button>
            <button className="ghost-action is-large" type="button" onClick={onOpenLogin}>
              <LogIn size={20} />
              Team login
            </button>
          </div>
          <div className="landing-stat-row" aria-label="Law platform statistics">
            <span>
              <strong>{articleCount}</strong>
              indexed articles
            </span>
            <span>
              <strong>{sourceCount}</strong>
              legal sources
            </span>
            <span>
              <strong>AI</strong>
              fact analysis
            </span>
          </div>
        </div>

        <div className="landing-product-panel" aria-label="Product preview">
          <div className="console-window">
            <div className="console-head">
              <span>Marokko Biz Law OS</span>
              <ShieldCheck size={18} />
            </div>
            <div className="preview-search">
              <Search size={18} />
              <span>Can my landlord evict me anytime?</span>
              <button type="button" onClick={onEnterWorkspace}>Search</button>
            </div>
            <div className="preview-answer">
              <span className="assistant-avatar">
                <Bot size={17} />
              </span>
              <div>
                <strong>Assistant analysis</strong>
                <p>
                  Extract facts first, spot the legal issues, retrieve Moroccan sources, reject unrelated
                  articles, then apply the law to the case.
                </p>
              </div>
            </div>
            <div className="source-stack">
              <article>
                <span>Source</span>
                <strong>Code des Obligations et des Contrats</strong>
                <small>Lease and contract obligations</small>
              </article>
              <article>
                <span>Article</span>
                <strong>Verified citation</strong>
                <small>Visible source links for human review</small>
              </article>
            </div>
            <div className="preview-metrics">
              <Metric icon={<BookOpen size={18} />} label="Articles" value={articleCount} />
              <Metric icon={<FileText size={18} />} label="Sources" value={sourceCount} />
            </div>
          </div>
        </div>
      </section>

      <section className="landing-grid" id="workflow" aria-label="Platform workflow">
        <article>
          <span>
            <Database size={18} />
          </span>
          <strong>01</strong>
          <h2>Search official sources</h2>
          <p>Indexed Moroccan legal texts stay searchable with article numbers, document titles, and source links.</p>
        </article>
        <article>
          <span>
            <Bot size={18} />
          </span>
          <strong>02</strong>
          <h2>Analyze real cases</h2>
          <p>The assistant reads the facts before citing law, then explains arguments, proof, limits, and likely outcome.</p>
        </article>
        <article>
          <span>
            <Languages size={18} />
          </span>
          <strong>03</strong>
          <h2>Translate and verify</h2>
          <p>Translate French legal excerpts when the team needs English or Arabic reading support.</p>
        </article>
      </section>

      <section className="payment-ready" id="access">
        <div>
          <span className="section-label">New domain ready</span>
          <h2>Separate law platform, with account access prepared for the team.</h2>
          <p>
            The landing page keeps Marokko Biz branding while preparing a dedicated legal product. Login is ready
            now, and payment can be connected later once the domain flow is confirmed.
          </p>
        </div>
        <div className="payment-card">
          <CreditCard size={22} />
          <strong>Account access ready</strong>
          <p>Clean login entry, mobile-first layout, and a reserved payment area for future configuration.</p>
          <button className="ghost-action" type="button" onClick={onOpenLogin}>
            Open team portal
          </button>
        </div>
      </section>

      {isLoginOpen ? (
        <div className="login-overlay" role="dialog" aria-modal="true" aria-label="Team login">
          <form
            className="login-modal"
            onSubmit={(event) => {
              event.preventDefault();
              onEnterWorkspace();
            }}
          >
            <button className="login-close" type="button" onClick={onCloseLogin} aria-label="Close login">
              <X size={18} />
            </button>
            <img src="/marokko-biz-icon.png" alt="Marokko Biz" />
            <h2>Team login</h2>
            <p>Use this account entry to access the law search engine on the new Marokko Biz domain.</p>
            <label>
              Email
              <input type="email" placeholder="name@company.com" autoComplete="email" defaultValue="MB@marokkobiz.com" />
            </label>
            <label>
              Password
              <input type="password" placeholder="Password" autoComplete="current-password" defaultValue="123456789" />
            </label>
            <button className="primary-action is-full" type="submit">
              Continue to workspace
              <ArrowRight size={18} />
            </button>
          </form>
        </div>
      ) : null}
    </main>
  );
}

function Metric({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <div className="metric-card">
      <span className="metric-icon">{icon}</span>
      <strong>{value}</strong>
      <span>{label}</span>
    </div>
  );
}

function ResultCard({
  law,
  index,
  translationState,
  onTranslate
}: {
  law: Law;
  index: number;
  translationState?: TranslationState;
  onTranslate: (law: Law, targetLanguage: "en" | "ar") => Promise<void>;
}) {
  const tags = normalizeTags(law.tags).slice(0, 5);
  const sourceParts = [law.document_title, law.law_reference, law.source_name].filter(Boolean);
  const isArabic = translationState?.data?.targetLanguage === "ar";

  return (
    <article className="result-card" style={{ animationDelay: `${Math.min(index, 8) * 35}ms` }}>
      <div className="result-topline">
        <span className="category-chip">{formatCategory(law.category)}</span>
        <span className="score-pill">{index === 0 ? "Best match" : "Verified source"}</span>
      </div>
      <h3>{law.title}</h3>
      <div className="article-meta-row">
        <span className="article-number">{law.article_number}</span>
        <span>{getLanguageLabel(law.language)}</span>
      </div>
      <p className="article-preview">{law.content}</p>

      {tags.length ? (
        <div className="tag-list">
          {tags.map((tag) => (
            <span className="tag" key={tag}>
              {tag}
            </span>
          ))}
        </div>
      ) : null}

      <div className="source-box">
        <div>
          <span>Source</span>
          <strong>{sourceParts.join(" | ") || "Official legal source"}</strong>
        </div>
        {law.source_url ? (
          <a href={law.source_url} target="_blank" rel="noreferrer">
            <ExternalLink size={16} />
            Open
          </a>
        ) : null}
      </div>

      <div className="action-row">
        <button type="button" onClick={() => void onTranslate(law, "en")} disabled={Boolean(translationState?.loading)}>
          {translationState?.loading === "en" ? <Loader2 className="spin" size={16} /> : <Languages size={16} />}
          {translationState?.active === "en" ? "Hide English" : "English"}
        </button>
        <button type="button" onClick={() => void onTranslate(law, "ar")} disabled={Boolean(translationState?.loading)}>
          {translationState?.loading === "ar" ? <Loader2 className="spin" size={16} /> : <Languages size={16} />}
          {translationState?.active === "ar" ? "Hide Arabic" : "Arabic"}
        </button>
        {translationState?.status ? <span>{translationState.status}</span> : null}
        {translationState?.fallbackUrl ? (
          <a href={translationState.fallbackUrl} target="_blank" rel="noreferrer">
            Open in Google Translate
          </a>
        ) : null}
      </div>

      {translationState?.active && translationState.data ? (
        <section className={`translation-box ${isArabic ? "is-rtl" : ""}`} dir={isArabic ? "rtl" : "ltr"}>
          <div className="translation-header">
            <CheckCircle2 size={16} />
            {getLanguageLabel(translationState.data.targetLanguage)} translation
          </div>
          <h4>{translationState.data.translatedTitle}</h4>
          <p>{translationState.data.translatedContent}</p>
        </section>
      ) : null}
    </article>
  );
}

createRoot(document.getElementById("root")!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
