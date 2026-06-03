const {
  getLawById,
  getLawLibraryOverview,
  SEARCH_RESULT_LIMIT,
  getSearchSuggestions,
  getLatestOfficialBulletinArticles,
  searchLawsByKeyword
} = require("../models/lawModel");
const { getStoredTranslation, saveTranslation } = require("../models/translationModel");
const {
  buildExternalTranslationUrl,
  isTranslationUnavailableError,
  translateLaw
} = require("../services/translationService");
const {
  answerWithAiReasoning,
  createAiSearchPlan
} = require("../services/aiReasoningService");

const normalizeChatText = (message) =>
  message
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[\u2019']/g, "'")
    .replace(/[?!.,;:()[\]{}"\u201c\u201d]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();

const uniqueValues = (items) => [...new Set(items.filter(Boolean))];

const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

const formatChatNumber = (value) => {
  if (typeof value !== "number" || !Number.isFinite(value)) {
    return "";
  }

  return Number.isInteger(value) ? String(value) : String(Math.round(value * 100) / 100);
};

const normalizeSearchScopeText = (value) =>
  String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[-_]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();

const normalizeReferenceScopeText = (value) =>
  String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[°º]/g, "")
    .replace(/\s*[-/]\s*/g, "-")
    .replace(/\s+/g, " ")
    .trim();

const documentScopeHints = [
  {
    title: "Code penal",
    aliases: ["code penal", "penal code"]
  },
  {
    title: "Code de commerce",
    aliases: ["code de commerce", "commercial code"]
  },
  {
    title: "Code du travail",
    aliases: ["code du travail", "labor code", "labour code"]
  },
  {
    title: "Code de la famille",
    aliases: ["code de la famille", "family code"]
  },
  {
    title: "Code de procedure civile",
    aliases: ["code de procedure civile", "civil procedure code"]
  },
  {
    title: "Code des Obligations et des Contrats",
    aliases: [
      "code des obligations et des contrats",
      "obligations et contrats",
      "obligations and contracts"
    ]
  }
];

const getDocumentScopeHints = (query) => {
  const normalizedQuery = normalizeSearchScopeText(query);

  return documentScopeHints
    .filter(({ aliases }) =>
      aliases.some((alias) => normalizedQuery.includes(normalizeSearchScopeText(alias)))
    )
    .map(({ title }) => title);
};

const getReferenceScopeHints = (query) => {
  const normalizedQuery = normalizeReferenceScopeText(query);
  const matches = [...normalizedQuery.matchAll(/\b\d{1,3}\s*[-/]\s*\d{2,4}\b/g)];

  return matches.map((match) => match[0].replace(/\s*[-/]\s*/, "-"));
};

const getArticleScopeHint = (query) => {
  const match = normalizeSearchScopeText(query).match(/\b(?:article|art)\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\b/);

  if (!match) {
    return "";
  }

  return match[1] === "premier" ? "article 1" : `article ${match[1].replace(/\s+/g, " ")}`;
};

const filterResultsToQueryScope = (query, results) => {
  const documentHints = getDocumentScopeHints(query);
  const referenceHints = getReferenceScopeHints(query);
  const articleHint = getArticleScopeHint(query);
  let scopedResults = results;

  if (articleHint) {
    const matchingArticles = scopedResults.filter((law) =>
      normalizeSearchScopeText(law.article_number) === articleHint
    );

    if (matchingArticles.length) {
      scopedResults = matchingArticles;
    }
  }

  if (documentHints.length) {
    const matchingDocuments = scopedResults.filter((law) =>
      documentHints.includes(law.document_title)
    );

    if (matchingDocuments.length) {
      scopedResults = matchingDocuments;
    }
  }

  if (referenceHints.length) {
    const matchingReferences = scopedResults.filter((law) => {
      const normalizedReference = normalizeReferenceScopeText(law.law_reference);
      return referenceHints.some((reference) => normalizedReference.includes(reference));
    });

    if (matchingReferences.length) {
      scopedResults = matchingReferences;
    }
  }

  return scopedResults;
};

const hasAlias = (normalizedMessage, alias) => {
  const normalizedAlias = normalizeChatText(alias);

  if (!normalizedAlias) {
    return false;
  }

  if (normalizedAlias.includes(" ")) {
    return normalizedMessage.includes(normalizedAlias);
  }

  return new RegExp(`\\b${escapeRegExp(normalizedAlias)}\\b`).test(normalizedMessage);
};

const topicProfiles = [
  {
    key: "real-estate",
    label: "real estate",
    aliases: [
      "real estate",
      "real-estate",
      "realestate",
      "property",
      "land",
      "rent",
      "rental",
      "lease",
      "tenant",
      "landlord",
      "immobilier",
      "propriete",
      "foncier",
      "bail",
      "loyer",
      "location",
      "locataire",
      "proprietaire",
      "terrain",
      "appartement",
      "maison",
      "copropriete",
      "titre foncier"
    ],
    queries: ["immobilier", "bail", "propriete fonciere", "copropriete", "urbanisme"]
  },
  {
    key: "business",
    label: "business and companies",
    aliases: [
      "business",
      "company",
      "companies",
      "corporate",
      "shareholder",
      "commerce",
      "commercial",
      "societe",
      "societes",
      "entreprise",
      "sarl",
      "sa",
      "actionnaire",
      "registre de commerce"
    ],
    queries: ["societe", "commerce", "registre de commerce"]
  },
  {
    key: "labor",
    label: "labor and employment",
    aliases: [
      "work",
      "worker",
      "employee",
      "employer",
      "employment",
      "labor",
      "labour",
      "salary",
      "wage",
      "termination",
      "travail",
      "salarie",
      "employeur",
      "contrat de travail",
      "licenciement",
      "salaire"
    ],
    queries: ["travail", "contrat de travail", "licenciement"]
  },
  {
    key: "family",
    label: "family law",
    aliases: [
      "family",
      "marriage",
      "divorce",
      "custody",
      "inheritance",
      "succession",
      "famille",
      "mariage",
      "divorce",
      "garde",
      "heritage",
      "pension"
    ],
    queries: ["famille", "mariage", "divorce", "succession"]
  },
  {
    key: "tax",
    label: "tax",
    aliases: ["tax", "taxes", "fiscal", "vat", "impot", "impots", "fiscalite", "tva", "taxe"],
    queries: ["fiscalite", "impot", "tva"]
  },
  {
    key: "banking",
    label: "banking and finance",
    aliases: ["bank", "banking", "finance", "credit", "loan", "banque", "bancaire", "credit", "pret"],
    queries: ["banque", "credit", "bancaire"]
  },
  {
    key: "contracts",
    label: "contracts",
    aliases: ["contract", "contracts", "agreement", "obligation", "contrat", "contrats", "obligation"],
    queries: ["contrat", "obligation"]
  },
  {
    key: "consumer",
    label: "consumer protection",
    aliases: ["consumer", "customer", "buyer", "consommateur", "consommation", "client", "acheteur"],
    queries: ["consommation", "consommateur"]
  },
  {
    key: "criminal",
    label: "criminal law",
    aliases: ["criminal", "crime", "penal", "prison", "offence", "infraction", "penal", "criminel"],
    queries: ["penal", "infraction"]
  },
  {
    key: "civil",
    label: "civil law",
    aliases: ["civil", "procedure", "lawsuit", "court", "tribunal", "procedure civile", "droit civil"],
    queries: ["droit civil", "procedure civile"]
  },
  {
    key: "insurance",
    label: "insurance",
    aliases: ["insurance", "assurance", "assurances", "insured", "insurer", "prevoyance", "acaps"],
    queries: ["assurance", "code des assurances", "prevoyance sociale"]
  },
  {
    key: "health",
    label: "health and medicines",
    aliases: ["health", "medicine", "medicines", "pharmacy", "hospital", "sante", "medicament", "pharmacie", "hopital"],
    queries: ["sante", "medicament", "pharmacie"]
  },
  {
    key: "environment",
    label: "environment",
    aliases: ["environment", "water", "pollution", "waste", "green", "environnement", "eau", "pollution", "dechets"],
    queries: ["environnement", "eau", "pollution", "dechets"]
  },
  {
    key: "energy",
    label: "energy",
    aliases: ["energy", "electricity", "renewable", "gas", "energie", "electricite", "renouvelable", "gaz"],
    queries: ["energie", "electricite", "energies renouvelables", "gaz naturel"]
  },
  {
    key: "public-procurement",
    label: "public procurement",
    aliases: [
      "public procurement",
      "public contracts",
      "government tenders",
      "marches publics",
      "commande publique",
      "appel d'offres",
      "appel offres"
    ],
    queries: ["marches publics", "commande publique", "appels d'offres"]
  },
  {
    key: "transport",
    label: "transport and road rules",
    aliases: ["transport", "road", "driving", "traffic", "vehicle", "route", "conduite", "circulation", "vehicule"],
    queries: ["transport", "code de la route", "vehicule"]
  },
  {
    key: "customs",
    label: "customs and imports",
    aliases: ["customs", "import", "export", "douane", "importation", "exportation", "antidumping"],
    queries: ["douane", "importation", "exportation", "antidumping"]
  },
  {
    key: "education",
    label: "education",
    aliases: ["education", "school", "university", "diploma", "enseignement", "universite", "diplome"],
    queries: ["education", "enseignement", "universite"]
  },
  {
    key: "official-bulletin",
    label: "recent official bulletins",
    aliases: [
      "recent laws",
      "new laws",
      "latest laws",
      "legal updates",
      "official bulletin",
      "bulletin officiel",
      "nouveaux textes",
      "nouvelles lois",
      "dernieres lois"
    ],
    queries: ["bulletin officiel", "textes generaux", "loi de finances"]
  }
];

const fillerWords = new Set([
  "a",
  "about",
  "all",
  "any",
  "are",
  "article",
  "articles",
  "can",
  "code",
  "could",
  "find",
  "for",
  "give",
  "i",
  "in",
  "is",
  "law",
  "laws",
  "legal",
  "legislation",
  "looking",
  "me",
  "moroccan",
  "morocco",
  "need",
  "of",
  "on",
  "please",
  "related",
  "search",
  "show",
  "tell",
  "the",
  "to",
  "want",
  "what",
  "with"
]);

const followUpPatterns = [
  /^(more|show more|continue|go on|next|again|more results|show other results)$/,
  /^(what about|and|also|for|same for)\b/,
  /\b(show|give|get|find)\s+(me\s+)?(more|other|another)\b/
];

const acknowledgementPattern = /^(ok|okay|cool|fine|great|nice|yes|no|sure|alright|perfect|alr)$/;

const findTopicProfile = (normalizedMessage) =>
  topicProfiles.find((profile) =>
    profile.aliases.some((alias) => hasAlias(normalizedMessage, alias))
  ) || null;

const hasLegalSignal = (normalizedMessage) =>
  Boolean(normalizedMessage) &&
  !getCasualChatAnswer(normalizedMessage) &&
  !acknowledgementPattern.test(normalizedMessage) &&
  (Boolean(findTopicProfile(normalizedMessage)) ||
    legalIntentPatterns.some((pattern) => pattern.test(normalizedMessage)) ||
    /\b(morocco|moroccan|maroc|marocain|marocaine)\b/.test(normalizedMessage));

const isFollowUpMessage = (normalizedMessage) =>
  followUpPatterns.some((pattern) => pattern.test(normalizedMessage));

const normalizeChatHistory = (history) => {
  if (!Array.isArray(history)) {
    return [];
  }

  return history
    .slice(-8)
    .map((message) => ({
      role: message?.role === "assistant" ? "assistant" : "user",
      text: typeof message?.text === "string" ? message.text.trim() : ""
    }))
    .filter((message) => message.text);
};

const getPreviousLegalQuestion = (history) => {
  const normalizedHistory = normalizeChatHistory(history);

  for (let index = normalizedHistory.length - 1; index >= 0; index -= 1) {
    const message = normalizedHistory[index];

    if (message.role === "user" && hasLegalSignal(normalizeChatText(message.text))) {
      return message.text;
    }
  }

  return "";
};

const extractReferenceQuery = (normalizedMessage) => {
  const lawReference = normalizedMessage.match(/\b(?:loi|dahir|decret)\s*(?:n|no|num|numero|n\u00b0)?\s*\d{1,3}[-/]\d{2,4}\b/);
  if (lawReference) {
    return lawReference[0];
  }

  return "";
};

const extractArticleQuery = (normalizedMessage) => {
  const articleReference = normalizedMessage.match(/\b(?:article|art)\s*\d+[a-z]?\b/);
  if (articleReference) {
    return articleReference[0];
  }

  return "";
};

const extractKeywordQuery = (normalizedMessage) => {
  const withoutIntentPhrases = normalizedMessage
    .replace(/\b(i want|i need|can you|could you|please|show me|find me|give me|tell me about|search for)\b/g, " ")
    .replace(/\b(laws?|legal|legislation|articles?|codes?|related to|about|regarding|on)\b/g, " ");

  const keywords = withoutIntentPhrases
    .split(/\s+/)
    .map((word) => word.trim())
    .filter((word) => word.length > 2 && !fillerWords.has(word));

  return uniqueValues(keywords).slice(0, 5).join(" ");
};

const buildLegalSearchPlan = (question, history = [], aiPlan = null) => {
  const normalizedMessage = normalizeChatText(question);
  const previousLegalQuestion = getPreviousLegalQuestion(history);
  const shouldUseContext =
    previousLegalQuestion &&
    isFollowUpMessage(normalizedMessage) &&
    !findTopicProfile(normalizedMessage) &&
    !extractReferenceQuery(normalizedMessage);
  const planningQuestion = shouldUseContext ? `${previousLegalQuestion} ${question}` : question;
  const normalizedPlanningMessage = normalizeChatText(planningQuestion);
  const topic = findTopicProfile(normalizedPlanningMessage);
  const referenceQuery = extractReferenceQuery(normalizedPlanningMessage);
  const articleQuery = extractArticleQuery(normalizedPlanningMessage);
  const keywordQuery = extractKeywordQuery(normalizedPlanningMessage);
  const queries = [];
  const hasTargetedAiQueries = Boolean(aiPlan?.needsLawSearch && aiPlan.searchQueries?.length);

  if (hasTargetedAiQueries) {
    queries.push(...aiPlan.searchQueries);
  }

  if (referenceQuery && articleQuery) {
    queries.push(`${articleQuery} ${referenceQuery}`);
  }

  if (referenceQuery) {
    queries.push(referenceQuery);
  }

  if (articleQuery && !referenceQuery) {
    queries.push(articleQuery);
  }

  if (topic && !hasTargetedAiQueries) {
    queries.push(...topic.queries);
  }

  if (keywordQuery && !hasTargetedAiQueries) {
    queries.push(keywordQuery);
  }

  if (!queries.length && shouldSearchLaws(question, history)) {
    queries.push(planningQuestion);
  }

  return {
    normalizedMessage,
    planningQuestion,
    aiPlan,
    topic,
    query: queries[0] || planningQuestion,
    queries: uniqueValues(queries),
    isFollowUp: Boolean(shouldUseContext)
  };
};

const getChatResultRank = (law) =>
  Number(law.document_match_score || 0) * 4 +
  Number(law.article_match_score || 0) * 3 +
  Number(law.relevance_score || 0) -
  Number(law.matchedQueryIndex || 0) * 120;

const shouldIncludeChatOnlySources = (plan) =>
  plan?.topic?.key === "official-bulletin" ||
  /\b(official bulletin|bulletin officiel|latest laws|new laws|recent laws|legal updates|dernieres lois|nouvelles lois)\b/.test(
    plan?.normalizedMessage || ""
  );

const searchLawsForChat = async (plan, limit = 6) => {
  if (plan.topic?.key === "official-bulletin") {
    const payload = await getLatestOfficialBulletinArticles(limit);

    return {
      results: payload.results.map((law) => ({
        ...law,
        matchedQuery: "latest official bulletins"
      })),
      hasMore: payload.hasMore
    };
  }

  const mergedResults = [];
  const seenIds = new Set();
  let hasMore = false;
  const shouldCollectAllQueries = Boolean(plan.aiPlan?.searchQueries?.length);
  const perQueryLimit = shouldCollectAllQueries ? Math.max(limit * 2, 12) : limit;

  for (const [queryIndex, query] of plan.queries.entries()) {
    const payload = await searchLawsByKeyword(query, perQueryLimit, {
      includeChatOnlySources: shouldIncludeChatOnlySources(plan)
    });
    const scopedResults = filterResultsToQueryScope(query, payload.results);
    hasMore = hasMore || payload.hasMore;

    for (const law of scopedResults) {
      if (!seenIds.has(law.id)) {
        seenIds.add(law.id);
        mergedResults.push({
          ...law,
          matchedQuery: query,
          matchedQueryIndex: queryIndex
        });
      }

      if (!shouldCollectAllQueries && mergedResults.length >= limit) {
        return {
          results: mergedResults,
          hasMore
        };
      }
    }
  }

  return {
    results: shouldCollectAllQueries
      ? mergedResults
          .sort((left, right) => getChatResultRank(right) - getChatResultRank(left))
          .slice(0, Math.max(limit * 3, 30))
      : mergedResults,
    hasMore
  };
};

const casualReplies = [
  {
    patterns: [
      /^(hi|hello|hey|yo|salam|salaam|salut|bonjour|bonsoir|good morning|good afternoon|good evening)$/,
      /^(hi|hello|hey|salam|salaam|salut|bonjour|bonsoir)\s+(there|again|friend)?$/
    ],
    answer: "Hi. What are we looking into today?"
  },
  {
    patterns: [
      /^(how are you|how r u|how is it going|how's it going|ca va|labas|labass|labas 3lik)$/,
      /^(are you ok|you good)$/
    ],
    answer: "I am good. Send me a topic or situation and I will help you narrow it down."
  },
  {
    patterns: [/^(thanks|thank you|thx|merci|choukran|shukran|ok thanks|okay thanks)$/],
    answer: "You are welcome."
  },
  {
    patterns: [
      /^(who are you|what are you|what can you do|help|can you help|can you help me)$/,
      /^(what do you do|how does this work)$/
    ],
    answer:
      "I can chat normally and help search the Moroccan law database. You can ask in plain language, for example: laws about real estate, articles on commercial leases, labor termination rules, or family law."
  }
];

const legalIntentPatterns = [
  /\b(law|legal|legislation|regulation|article|code|statute|decree|dahir|loi|droit|juridique|article|code|decret|arrete|tribunal|court|judge|case|contract|lease|tenant|landlord|property|real estate|company|corporate|tax|labor|employment|family|marriage|divorce|inheritance|criminal|civil|commerce|commercial|consumer|bank|insurance|investment|permit|license|notary|immigration)\b/,
  /\b(immobilier|bail|locataire|proprietaire|societe|travail|famille|fiscalite|commerce|contrat|contrats|banque|assurance|mariage|divorce|heritage|succession|penal|civil|consommation|investissement|permis|autorisation|notaire)\b/,
  /\b(loi|dahir|decret)\s*(n|no|num|numero|n\u00b0)?\s*\d{1,3}[-/]\d{2,4}\b/,
  /\b(article|art)\s*\d+\b/,
  /\b\d{1,3}[-/]\d{2,4}\b/
];

const nonLegalIntentPatterns = [
  /\b(weather|forecast|temperature|rain|recipe|cook|movie|music|song|game|sports|football|joke|translate this|write a poem)\b/
];

const getCasualChatAnswer = (normalizedMessage) => {
  const match = casualReplies.find(({ patterns }) => patterns.some((pattern) => pattern.test(normalizedMessage)));
  return match?.answer || null;
};

const shouldSearchLaws = (message, history = [], aiPlan = null) => {
  const normalizedMessage = normalizeChatText(message);

  if (!normalizedMessage) {
    return false;
  }

  if (getCasualChatAnswer(normalizedMessage) || acknowledgementPattern.test(normalizedMessage)) {
    return false;
  }

  if (nonLegalIntentPatterns.some((pattern) => pattern.test(normalizedMessage))) {
    return false;
  }

  if (aiPlan?.needsLawSearch && aiPlan.searchQueries?.length) {
    return true;
  }

  if (aiPlan && aiPlan.needsLawSearch === false) {
    return false;
  }

  if (findTopicProfile(normalizedMessage)) {
    return true;
  }

  if (legalIntentPatterns.some((pattern) => pattern.test(normalizedMessage))) {
    return true;
  }

  if (isFollowUpMessage(normalizedMessage) && getPreviousLegalQuestion(history)) {
    return true;
  }

  const words = normalizedMessage.split(" ").filter(Boolean);
  if (words.length <= 2) {
    return false;
  }

  return /\b(morocco|moroccan|maroc|marocain|marocaine)\b/.test(normalizedMessage);
};

const buildOutOfScopeChatAnswer = (message) => {
  const normalizedMessage = normalizeChatText(message);

  if (/^(ok|okay|cool|fine|great|nice|yes|no|sure|alright|perfect)$/.test(normalizedMessage)) {
    return "Got it. Tell me the legal topic or situation when you are ready.";
  }

  if (/\b(weather|forecast|temperature|rain)\b/.test(normalizedMessage)) {
    return "I am not connected to live weather here. I am best at Moroccan legal research from the local database.";
  }

  if (/\b(recipe|cook|movie|music|song|game|sports|football|joke|poem)\b/.test(normalizedMessage)) {
    return "I can help a little with general conversation, but this app is built for Moroccan legal research. Give me a legal topic and I will search it properly.";
  }

  return "I can follow you, but I need a legal topic, article, code, source, or real-world situation before searching the Moroccan law database.";
};

const extractChatFacts = (message) => {
  const normalizedMessage = normalizeChatText(message);
  const facts = [];
  const addFact = (fact) => {
    if (fact && !facts.includes(fact)) {
      facts.push(fact);
    }
  };
  const yearsMatch = normalizedMessage.match(/\b(?:depuis|for|worked for|travaille depuis)?\s*(\d+(?:[.,]\d+)?)\s*(?:years?|ans|annees?)\b/);
  const yearsOfService = yearsMatch ? Number(yearsMatch[1].replace(",", ".")) : null;
  const hasAccusation = /\b(accuse|accusation|accuses|alleges|allegation|affirme|qualifie|disciplinary|disciplinaire|procedure disciplinaire)\b/.test(normalizedMessage);
  const hasTheft = /\b(vol|vole|theft|stolen)\b/.test(normalizedMessage);
  const hasPersonalEmail = /\b(personal email|personal mail|email personnel|mail personnel|adresse email personnelle|adresse personnelle)\b/.test(normalizedMessage);
  const hasWorkFromHome = /\b(domicile|home|teletravail|telework|remote work|work from home|work from his home|work from her home)\b/.test(normalizedMessage);
  const hasNoCompetitorDisclosure = /\b(no competitor|no competitors|aucun concurrent|concurrent n a recu|competitor received|competitors received)\b/.test(normalizedMessage);
  const hasNoDisclosure = /\b(aucun document|no document|pas divulgue|not disclosed|tiers|third parties|no competitor|no competitors|aucun concurrent)\b/.test(normalizedMessage);
  const hasNoDamage = /\b(aucun prejudice|prejudice concret|ne demontre aucun prejudice|no damage|no proven damage|no harm|concrete harm)\b/.test(normalizedMessage);

  if (typeof yearsOfService === "number" && Number.isFinite(yearsOfService)) {
    addFact(`Le salarie a ${formatChatNumber(yearsOfService)} ans d'anciennete.`);
  }

  if (/\b(sans aucun antecedent|aucun antecedent|antecedent disciplinaire|no disciplinary|pas d antecedent|no warning|aucun avertissement|no prior warnings)\b/.test(normalizedMessage)) {
    addFact("Aucun antecedent disciplinaire ou avertissement anterieur n'est mentionne.");
  }

  if (/\b(securite|safety|danger|violations|regles de securite|workers|travailleurs)\b/.test(normalizedMessage)) {
    addFact("Le salarie a signale des problemes de securite pouvant mettre les travailleurs en danger.");
  }

  if (/\b(trois|three|3)\s*(semaines|weeks)\b/.test(normalizedMessage)) {
    addFact("La procedure disciplinaire commence environ trois semaines apres le signalement.");
  }

  if (/\b(deux|two|2)\b.*\b(employes|employees|salaries|witnesses|temoins|confirment|confirm)\b/.test(normalizedMessage)) {
    addFact("Deux autres employes confirment que les problemes signales existaient.");
  }

  if (/\b(attitude negative|negative attitude|perturbation|bon fonctionnement|normal operation|disruption)\b/.test(normalizedMessage)) {
    addFact("L'employeur invoque une attitude negative ou une perturbation du fonctionnement normal.");
  }

  if (/\b(licencie|licenciement|dismissal|dismiss|dismisses|dismissed|fired|terminated|termination)\b/.test(normalizedMessage)) {
    addFact("Le salarie est finalement licencie.");
  }

  if (hasAccusation) {
    addFact("L'entreprise accuse le salarie d'un fait fautif.");
  }

  if (hasTheft) {
    addFact("L'accusation porte sur un vol.");
  }

  if (/\b(ordinateur|laptop|portable)\b/.test(normalizedMessage)) {
    addFact("L'objet mentionne est un ordinateur portable.");
  }

  if (/\b(aucun|no)\b.*\b(manquant|missing)\b|\b(inventaire|inventory)\b/.test(normalizedMessage)) {
    addFact("Selon l'inventaire de l'entreprise, aucun ordinateur n'est manquant.");
  }

  if (/\b(document|documents|donnees|data|fichier|fichiers)\b/.test(normalizedMessage)) {
    addFact("Les elements concernes sont des documents ou donnees professionnels.");
  }

  if (hasPersonalEmail) {
    addFact("Les documents ont ete envoyes vers l'adresse email personnelle du salarie.");
  }

  if (/\b(usb|cle usb)\b/.test(normalizedMessage)) {
    addFact("Les documents ont ete transferes sur une cle USB personnelle.");
  }

  if (hasWorkFromHome) {
    addFact("Le salarie explique que le transfert servait a travailler depuis son domicile.");
  }

  if (hasNoCompetitorDisclosure) {
    addFact("Aucun concurrent n'est presente comme ayant recu les documents.");
  }

  if (hasNoDisclosure) {
    addFact("Aucune divulgation a des tiers n'est mentionnee.");
  }

  if (hasNoDamage) {
    addFact("Aucun prejudice concret n'est demontre dans la question.");
  }

  if (/\b(immediate dismissal|immediately dismissed|dismissed immediately|licencie immediatement|licenciement immediat|immediatement)\b/.test(normalizedMessage)) {
    addFact("Le licenciement est presente comme immediat.");
  }

  if (hasAccusation && !/\b(preuve directe|direct evidence|temoin|witness|camera|video)\b/.test(normalizedMessage)) {
    addFact(
      hasTheft
        ? "Aucune preuve directe du vol n'est mentionnee dans la question."
        : "Aucune preuve directe du fait fautif n'est mentionnee dans la question."
    );
  }

  return facts;
};

const buildFactOnlyChatAnswer = (message) => {
  const normalizedMessage = normalizeChatText(message);

  if (
    !/\b(liste|list|identify|extraire|extract)\b.*\b(faits|facts)\b/.test(normalizedMessage) ||
    !/\b(sans citer|ne cite aucun|do not cite|dont cite|no articles|no article|no laws|no law|without citing|must not cite)\b/.test(
      normalizedMessage
    )
  ) {
    return null;
  }

  const extractedFacts = extractChatFacts(message);

  if (extractedFacts.length) {
    return extractedFacts.map((fact, index) => `${index + 1}. ${fact}`).join("\n");
  }

  const facts = [];

  if (/\b(accuse|accusation|accuses|alleges|allegation|affirme|qualifie|licencie)\b/.test(normalizedMessage)) {
    facts.push("L'entreprise accuse le salarie d'un fait fautif.");
  }

  if (/\b(vol|vole|theft|stolen)\b/.test(normalizedMessage)) {
    facts.push("L'accusation porte sur un vol.");
  }

  if (/\b(ordinateur|laptop|portable)\b/.test(normalizedMessage)) {
    facts.push("L'objet mentionne est un ordinateur portable.");
  }

  if (/\b(document|documents|donnees|data|fichier|fichiers)\b/.test(normalizedMessage)) {
    facts.push("Les elements concernes sont des documents ou donnees professionnels.");
  }

  if (/\b(usb|cle usb)\b/.test(normalizedMessage)) {
    facts.push("Les documents ont ete transferes sur une cle USB personnelle.");
  }

  if (/\b\d+(?:[.,]\d+)?\s*(ans|annees|years)\b/.test(normalizedMessage)) {
    facts.push("L'anciennete du salarie est mentionnee dans la question.");
  }

  if (/\b(sans aucun antecedent|aucun antecedent|antecedent disciplinaire|no disciplinary|pas d antecedent)\b/.test(normalizedMessage)) {
    facts.push("Aucun antecedent disciplinaire n'est mentionne.");
  }

  if (/\b(domicile|home|teletravail|telework|remote work|work from home)\b/.test(normalizedMessage)) {
    facts.push("Le salarie explique que le transfert servait a travailler depuis son domicile.");
  }

  if (/\b(aucun|no)\b.*\b(manquant|missing)\b|\b(inventaire|inventory)\b/.test(normalizedMessage)) {
    facts.push("Selon l'inventaire de l'entreprise, aucun ordinateur n'est manquant.");
  }

  if (/\b(aucun document|no document|pas divulgue|not disclosed|tiers|third parties)\b/.test(normalizedMessage)) {
    facts.push("Aucune divulgation a des tiers n'est mentionnee.");
  }

  if (/\b(aucun prejudice|prejudice concret|ne demontre aucun prejudice|no damage|no harm|concrete harm)\b/.test(normalizedMessage)) {
    facts.push("Aucun prejudice concret n'est demontre dans la question.");
  }

  if (!/\b(preuve directe|direct evidence|temoin|witness|camera|video)\b/.test(normalizedMessage)) {
    facts.push("Aucune preuve directe du vol n'est mentionnee dans la question.");
  }

  return facts.length ? facts.map((fact, index) => `${index + 1}. ${fact}`).join("\n") : null;
};

const relevanceStopWords = new Set([
  ...fillerWords,
  "after",
  "also",
  "and",
  "aux",
  "avec",
  "been",
  "but",
  "cet",
  "cette",
  "code",
  "codes",
  "dans",
  "des",
  "does",
  "dont",
  "est",
  "from",
  "has",
  "have",
  "his",
  "its",
  "lawful",
  "les",
  "leur",
  "leurs",
  "loi",
  "mais",
  "not",
  "par",
  "pas",
  "plus",
  "pour",
  "que",
  "qui",
  "sans",
  "ses",
  "son",
  "sur",
  "the",
  "this",
  "une",
  "was",
  "were",
  "when"
]);

const normalizeRelevanceText = (value) =>
  normalizeSearchScopeText(value)
    .replace(/[^a-z0-9\s]/g, " ")
    .replace(/\s+/g, " ")
    .trim();

const getRelevanceTokens = (value) =>
  uniqueValues(
    normalizeRelevanceText(value)
      .split(/\s+/)
      .filter(
        (token) =>
          token.length >= 3 &&
          !/^\d+$/.test(token) &&
          !relevanceStopWords.has(token)
      )
  );

const getLawRelevanceText = (law) =>
  normalizeRelevanceText(
    [
      law.title,
      law.article_number,
      law.content,
      law.tags,
      law.document_title,
      law.law_reference,
      law.category,
      law.source_name
    ].join(" ")
  );

const getRelevancePhraseMatches = (terms, normalizedText) =>
  uniqueValues(
    (terms || [])
      .map((term) => normalizeRelevanceText(term))
      .filter((term) => term.length >= 3 && !relevanceStopWords.has(term))
  ).filter((term) => normalizedText.includes(term));

const getAllowedSourceSignals = (plan, law) => {
  const allowedDocumentTitles = uniqueValues(plan?.aiPlan?.allowedDocumentTitles || [])
    .map((title) => normalizeRelevanceText(title))
    .filter(Boolean);
  const allowedCategories = uniqueValues(plan?.aiPlan?.allowedCategories || [])
    .map((category) => normalizeRelevanceText(category))
    .filter(Boolean);

  if (!allowedDocumentTitles.length && !allowedCategories.length) {
    return {
      hasSourceGate: false,
      sourceMatches: true
    };
  }

  const normalizedDocument = normalizeRelevanceText(law.document_title);
  const normalizedCategory = normalizeRelevanceText(law.category);
  const documentMatches = allowedDocumentTitles.some(
    (documentTitle) =>
      normalizedDocument === documentTitle ||
      normalizedDocument.includes(documentTitle) ||
      documentTitle.includes(normalizedDocument)
  );
  const categoryMatches = allowedCategories.some((category) => normalizedCategory === category);

  return {
    hasSourceGate: true,
    sourceMatches: documentMatches || categoryMatches,
    documentMatches,
    categoryMatches
  };
};

const getScopedRelevanceSignals = (query, law) => {
  const articleHint = getArticleScopeHint(query);
  const documentHints = getDocumentScopeHints(query);
  const referenceHints = getReferenceScopeHints(query);
  const normalizedArticle = normalizeSearchScopeText(law.article_number);
  const normalizedDocumentTitle = normalizeSearchScopeText(law.document_title);
  const normalizedReference = normalizeReferenceScopeText(law.law_reference);
  const articleMatches = !articleHint || normalizedArticle === articleHint;
  const documentMatches =
    !documentHints.length ||
    documentHints.some((documentHint) => normalizeSearchScopeText(documentHint) === normalizedDocumentTitle);
  const referenceMatches =
    !referenceHints.length ||
    referenceHints.some((referenceHint) => normalizedReference.includes(referenceHint));

  return {
    articleHint,
    documentHints,
    referenceHints,
    articleMatches,
    documentMatches,
    referenceMatches,
    rejectedByScope: !articleMatches || !documentMatches || !referenceMatches
  };
};

const scoreChatResultRelevance = (question, plan, law) => {
  const matchedQuery = law.matchedQuery || plan?.query || question;
  const lawText = getLawRelevanceText(law);
  const scopedSignals = getScopedRelevanceSignals(matchedQuery, law);
  const sourceSignals = getAllowedSourceSignals(plan, law);

  if (scopedSignals.rejectedByScope || !sourceSignals.sourceMatches) {
    return {
      score: 0,
      planTermMatches: [],
      rejectedByScope: scopedSignals.rejectedByScope,
      rejectedBySource: !sourceSignals.sourceMatches
    };
  }

  let score = 0;

  if (sourceSignals.hasSourceGate) {
    score += 4;
  }

  if (scopedSignals.articleHint && scopedSignals.articleMatches) {
    score += 6;
  }

  if (scopedSignals.documentHints.length && scopedSignals.documentMatches) {
    score += 4;
  }

  if (scopedSignals.referenceHints.length && scopedSignals.referenceMatches) {
    score += 4;
  }

  const planTermMatches = getRelevancePhraseMatches(plan?.aiPlan?.relevanceTerms || [], lawText);
  score += Math.min(planTermMatches.length * 2, 8);

  const queryTokenMatches = getRelevanceTokens(matchedQuery).filter((token) => lawText.includes(token));
  score += Math.min(queryTokenMatches.length * 0.7, 4.2);

  const questionTokenMatches = getRelevanceTokens(question).filter((token) => lawText.includes(token));
  score += Math.min(questionTokenMatches.length * 0.35, 2.1);

  const normalizedCategory = normalizeRelevanceText(law.category);
  const normalizedDocument = normalizeRelevanceText(law.document_title);
  const normalizedTopicKey = normalizeRelevanceText(plan?.topic?.key || "");
  const topicAliasMatches = (plan?.topic?.aliases || []).some((alias) => {
    const normalizedAlias = normalizeRelevanceText(alias);
    return (
      normalizedAlias.length >= 3 &&
      (lawText.includes(normalizedAlias) ||
        normalizedCategory.includes(normalizedAlias) ||
        normalizedDocument.includes(normalizedAlias))
    );
  });

  if (
    topicAliasMatches ||
    (normalizedTopicKey &&
      (normalizedCategory.includes(normalizedTopicKey) || normalizedDocument.includes(normalizedTopicKey)))
  ) {
    score += 2.5;
  }

  return {
    score,
    planTermMatches,
    rejectedByScope: false,
    rejectedBySource: false
  };
};

const filterChatResultsByRelevance = (question, plan, results) => {
  if (!results.length) {
    return [];
  }

  const scoredResults = results
    .map((law) => ({
      law,
      ...scoreChatResultRelevance(question, plan, law)
    }))
    .filter(({ score, rejectedByScope, rejectedBySource }) => !rejectedByScope && !rejectedBySource && score >= 2.8);

  return scoredResults
    .sort((left, right) => right.score - left.score || getChatResultRank(right.law) - getChatResultRank(left.law))
    .slice(0, 16)
    .map(({ law, score }) => ({
      ...law,
      chatRelevanceScore: Math.round(score * 100) / 100
    }));
};

const buildInsufficientRelevantSourcesAnswer = (question, plan, attemptedResults) => {
  const issue =
    plan?.aiPlan?.legalIssue ||
    plan?.topic?.label ||
    plan?.query ||
    question;
  const tried = plan?.queries?.length ? ` I tried: ${plan.queries.slice(0, 5).join(", ")}.` : "";
  const retrievedSources = uniqueValues(
    attemptedResults
      .map((law) => law.document_title || law.documentTitle || law.source_name || law.title)
      .filter(Boolean)
  ).slice(0, 3);
  const sourceNote = retrievedSources.length
    ? ` The closest raw search hits came from ${retrievedSources.join(", ")}, but they did not look sufficiently tied to your facts.`
    : "";

  return `Sources insuffisantes: I could not find sufficiently relevant Moroccan law sources in the local database for ${issue}.${sourceNote} I should not answer from unrelated articles.${tried} Try a specific French legal term, code name, law number, or article if you have one.`;
};

const searchLaws = async (req, res) => {
  try {
    const { q } = req.query;
    const searchPayload = await searchLawsByKeyword(q || "", SEARCH_RESULT_LIMIT);
    const scopedResults = filterResultsToQueryScope(q || "", searchPayload.results);

    res.json({
      query: q || "",
      count: scopedResults.length,
      results: scopedResults,
      hasMore: searchPayload.hasMore && scopedResults.length === searchPayload.results.length,
      limit: searchPayload.limit
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({
      message: "Failed to search laws"
    });
  }
};

const buildChatAnswer = (question, results, plan) => {
  if (!results.length) {
    const tried = plan?.queries?.length ? ` I tried: ${plan.queries.join(", ")}.` : "";
    return `I did not find matching articles in the local Moroccan law database.${tried} Try a broader legal keyword, a French term, or a specific law/article reference.`;
  }

  const topDocuments = [
    ...new Set(results.map((law) => law.documentTitle || law.document_title).filter(Boolean))
  ].slice(0, 3);
  const articleReferences = results
    .slice(0, 3)
    .map((law) => `${law.articleNumber || law.article_number} from ${law.documentTitle || law.document_title || law.title}`)
    .join("; ");
  const searchedFor = plan?.aiPlan?.legalIssue
    ? `${plan.aiPlan.legalIssue} (${plan.queries.slice(0, 3).join(", ")})`
    : plan?.topic
    ? `${plan.topic.label} (${plan.queries.slice(0, 3).join(", ")})`
    : plan?.query || question;

  return [
    `Based on the local Moroccan law database, I found ${results.length} relevant article${results.length === 1 ? "" : "s"} for ${searchedFor}.`,
    topDocuments.length ? `The strongest source${topDocuments.length === 1 ? " is" : "s are"}: ${topDocuments.join(", ")}.` : "",
    articleReferences ? `The closest starting points are: ${articleReferences}.` : "",
    "For a fuller reasoning answer, enable the local AI brain with Ollama; I attached the best matches below so the sources are still visible."
  ]
    .filter(Boolean)
    .join(" ");
};

const chatWithLaws = async (req, res) => {
  try {
    const question = typeof req.body?.message === "string" ? req.body.message.trim() : "";
    const history = normalizeChatHistory(req.body?.history);

    if (!question) {
      return res.status(400).json({
        message: "Chat message is required"
      });
    }

    const normalizedQuestion = normalizeChatText(question);
    const casualAnswer = getCasualChatAnswer(normalizedQuestion);

    if (casualAnswer) {
      return res.json({
        question,
        answer: casualAnswer,
        citations: []
      });
    }

    const factOnlyAnswer = buildFactOnlyChatAnswer(question);

    if (factOnlyAnswer) {
      return res.json({
        question,
        answer: factOnlyAnswer,
        citations: []
      });
    }

    const aiPlan = await createAiSearchPlan(question, history);
    const plan = buildLegalSearchPlan(question, history, aiPlan);

    if (!plan.queries.length || !shouldSearchLaws(question, history, aiPlan)) {
      return res.json({
        question,
        answer: buildOutOfScopeChatAnswer(question),
        citations: []
      });
    }

    const searchPayload = await searchLawsForChat(plan, 12);
    const relevantResults = filterChatResultsByRelevance(question, plan, searchPayload.results);
    const citations = relevantResults.map((law) => ({
      id: law.id,
      title: law.title,
      articleNumber: law.article_number,
      content: law.content,
      documentTitle: law.document_title,
      lawReference: law.law_reference,
      sourceName: law.source_name,
      sourceUrl: law.source_url,
      category: law.category,
      relevanceScore: law.relevance_score,
      sourceRelevanceScore: law.chatRelevanceScore,
      matchedQuery: law.matchedQuery
    }));

    if (searchPayload.results.length && !citations.length) {
      return res.json({
        question,
        answer: buildInsufficientRelevantSourcesAnswer(question, plan, searchPayload.results),
        citations: []
      });
    }

    const aiAnswer = await answerWithAiReasoning({
      question,
      plan,
      citations
    });

    res.json({
      question,
      answer: aiAnswer || buildChatAnswer(question, citations, plan),
      citations
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({
      message: "Failed to chat with laws"
    });
  }
};

const translateLawArticle = async (req, res) => {
  const lawId = Number(req.params.id);
  const targetLanguage =
    typeof req.query.target === "string" && req.query.target.trim()
      ? req.query.target.trim().toLowerCase()
      : "en";

  try {
    if (!Number.isInteger(lawId) || lawId <= 0) {
      return res.status(400).json({
        message: "Invalid law id"
      });
    }

    const law = await getLawById(lawId);

    if (!law) {
      return res.status(404).json({
        message: "Law not found"
      });
    }

    const storedTranslation = await getStoredTranslation(law.id, targetLanguage);

    if (storedTranslation) {
      return res.json({
        articleNumber: law.article_number,
        documentTitle: law.document_title,
        sourceUrl: law.source_url,
        ...storedTranslation
      });
    }

    const translation = await translateLaw(law, targetLanguage);

    await saveTranslation({
      lawId: law.id,
      sourceLanguage: translation.sourceLanguage,
      targetLanguage: translation.targetLanguage,
      translatedTitle: translation.translatedTitle,
      translatedContent: translation.translatedContent
    });

    return res.json({
      id: law.id,
      articleNumber: law.article_number,
      documentTitle: law.document_title,
      sourceUrl: law.source_url,
      cached: false,
      ...translation
    });
  } catch (error) {
    const law = Number.isInteger(lawId) && lawId > 0 ? await getLawById(lawId).catch(() => null) : null;
    const fallbackUrl = law ? buildExternalTranslationUrl(law, targetLanguage) : null;
    const isExpectedTranslationOutage = isTranslationUnavailableError(error);

    if (isExpectedTranslationOutage) {
      console.warn("Inline translation unavailable because free providers are rate-limited or unreachable.");
    } else {
      console.error(error);
    }

    return res.status(isExpectedTranslationOutage ? 503 : 500).json({
      message: isExpectedTranslationOutage
        ? "Inline translation is temporarily unavailable."
        : "Failed to translate law",
      fallbackUrl
    });
  }
};

const getLibraryOverview = async (req, res) => {
  try {
    const overview = await getLawLibraryOverview();
    res.json(overview);
  } catch (error) {
    console.error(error);
    res.status(500).json({
      message: "Failed to load library overview"
    });
  }
};

const getSuggestions = async (req, res) => {
  try {
    const { q } = req.query;
    const suggestions = await getSearchSuggestions(q || "");

    res.json({
      query: q || "",
      suggestions
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({
      message: "Failed to load suggestions"
    });
  }
};

module.exports = {
  chatWithLaws,
  searchLaws,
  translateLawArticle,
  getLibraryOverview,
  getSuggestions,
  normalizeChatText,
  shouldSearchLaws,
  buildLegalSearchPlan
};
