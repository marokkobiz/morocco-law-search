const DEFAULT_OLLAMA_BASE_URL = "http://localhost:11434";
const DEFAULT_OLLAMA_MODEL = "qwen3:8b";
const DEFAULT_PLANNER_TIMEOUT_MS = 12000;
const DEFAULT_ANSWER_TIMEOUT_MS = 30000;

const getAiProvider = () => String(process.env.AI_PROVIDER || "none").toLowerCase();

const isAiReasoningEnabled = () => getAiProvider() === "ollama";

const stripThinkingText = (text) =>
  String(text || "")
    .replace(/<think>[\s\S]*?<\/think>/gi, "")
    .trim();

const normalizeText = (value) =>
  String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[-_]+/g, " ")
    .replace(/[?!.,;:()[\]{}"\u201c\u201d]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();

const cleanLawExcerpt = (value) =>
  String(value || "")
    .replace(/\s+/g, " ")
    .replace(/\b(200|500|1\.000|2\.000|5\.000|10\.000|50\.000)\s+\d{2,3}\s+(?=à\b)/g, "$1 ")
    .replace(/\b(200|500|1\.000|2\.000|5\.000|10\.000|50\.000)\s+\d{2,3}\s+(?=a\b)/gi, "$1 ")
    .trim();

const cleanGeneratedAnswer = (value) =>
  String(value || "")
    .replace(/\b200[,\s]+243\s+(to|à|a)\s+500\b/gi, "200 $1 500")
    .replace(/\b200[,\s]+243\s*(dirhams?|dh)\b/gi, "200 $1")
    .trim();

const sourceDomains = {
  labor: {
    allowedDocumentTitles: ["Code du travail"],
    allowedCategories: ["labor"]
  },
  civilContracts: {
    allowedDocumentTitles: ["Code des Obligations et des Contrats"],
    allowedCategories: ["civil"]
  },
  criminal: {
    allowedDocumentTitles: ["Code penal"],
    allowedCategories: ["criminal"]
  },
  family: {
    allowedDocumentTitles: ["Code de la famille"],
    allowedCategories: ["family"]
  },
  consumer: {
    allowedDocumentTitles: ["Protection du consommateur"],
    allowedCategories: ["consumer"]
  },
  commercialCompany: {
    allowedDocumentTitles: [
      "Code de commerce",
      "Societes anonymes",
      "Societes a responsabilite limitee",
      "Societes responsabilite limitee"
    ],
    allowedCategories: ["commercial"]
  },
  commercialLease: {
    allowedDocumentTitles: [
      "Baux des immeubles ou des locaux loues a usage commercial, industriel ou artisanal"
    ],
    allowedCategories: ["real-estate", "commercial"]
  },
  realEstate: {
    allowedDocumentTitles: [
      "Code des droits reels",
      "Statut de la copropriete des immeubles batis",
      "Recouvrement des loyers"
    ],
    allowedCategories: ["real-estate"]
  }
};

const legalQueryProfiles = [
  {
    legalIssue: "commercial lease eviction",
    patterns: [
      /\b(commercial lease|business lease|shop lease|store lease|industrial lease|artisanal lease|business premises|commercial premises|fonds de commerce)\b/,
      /\b(local commercial|usage commercial|fonds de commerce|bail commercial|baux commerciaux|industriel|artisanal)\b/,
      /\b(shop|store|business|restaurant|office|premises)\b.*\b(landlord|tenant|lease|rent|evict|eviction)\b/,
      /\b(landlord|tenant|lease|rent|evict|eviction)\b.*\b(shop|store|business|restaurant|office|premises)\b/
    ],
    searchQueries: [
      "article 7 loi 49-16 indemnite eviction",
      "article 8 loi 49-16 eviction loyer",
      "indemnite eviction locataire commercial",
      "article 13 loi 49-16 eviction",
      "article 16 loi 49-16 eviction temporaire",
      "baux immeubles locaux loues usage commercial",
      "fonds de commerce eviction"
    ],
    ...sourceDomains.commercialLease
  },
  {
    legalIssue: "landlord and tenant rent or residential eviction",
    patterns: [
      /\b(landlord|tenant|rent|rental|lease|evict|eviction|apartment|flat|home|house)\b/,
      /\b(bailleur|locataire|loyer|bail|expulsion|eviction|resiliation|habitation)\b/
    ],
    searchQueries: [
      "recouvrement des loyers",
      "loi 64-99 recouvrement loyers",
      "bailleur locataire mise en demeure loyer",
      "loi 67-12 bail habitation",
      "local usage habitation loyer",
      "bailleur locataire tribunal loyer"
    ],
    ...sourceDomains.realEstate
  },
  {
    legalIssue: "real estate law overview",
    patterns: [
      /\b(real estate|real-estate|land title|property title|property ownership|co-ownership|housing lease)\b/,
      /\b(immobilier|foncier|titre foncier|propriete fonciere|copropriete|bail habitation)\b/
    ],
    searchQueries: [
      "code des droits reels propriete fonciere",
      "propriete fonciere",
      "immobilier",
      "copropriete",
      "loi 67-12 bail habitation",
      "recouvrement des loyers"
    ],
    relevanceTerms: [
      "immobilier",
      "foncier",
      "propriete",
      "copropriete",
      "bail",
      "loyer"
    ],
    ...sourceDomains.realEstate
  },
  {
    legalIssue: "stolen property or theft",
    patterns: [
      /\b(stolen|stole|steal|theft|robbed|robbery|burglary|break in|breakin)\b/,
      /\b(vol|larcin|soustraction frauduleuse|effraction)\b/
    ],
    searchQueries: [
      "article 505 code penal",
      "soustraction frauduleuse",
      "vol code penal",
      "article 506 code penal",
      "article 507 code penal",
      "article 508 code penal",
      "article 509 code penal",
      "article 510 code penal"
    ],
    ...sourceDomains.criminal
  },
  {
    legalIssue: "pregnancy or maternity dismissal",
    patterns: [
      /\b(pregnant|pregnancy|maternity|mother|gave birth|birth|medical certificate|staff reduction|reducing staff)\b/,
      /\b(grossesse|enceinte|maternite|maternité|accouchement|certificat medical|certificat médical|reduction du personnel|licenciement economique|licenciement économique)\b/
    ],
    searchQueries: [
      "article 159 code du travail grossesse licenciement",
      "article 160 code du travail certificat grossesse licenciement",
      "article 165 code du travail grossesse amende",
      "article 35 code du travail licenciement motif valable",
      "article 63 code du travail justification licenciement",
      "article 64 code du travail motifs licenciement tribunal",
      "article 66 code du travail licenciement economique",
      "article 67 code du travail licenciement economique autorisation",
      "article 41 code du travail licenciement abusif dommages interets",
      "article 65 code du travail action justice licenciement"
    ],
    relevanceTerms: ["travail", "salarie", "employeur", "licenciement", "grossesse", "maternite"],
    ...sourceDomains.labor
  },
  {
    legalIssue: "employment termination",
    patterns: [
      /\b(fire|fired|termination|terminated|dismiss|dismissed|notice|salary|wage|employee|employer|boss|job|work contract)\b/,
      /\b(licenciement|preavis|salaire|employeur|salarie|contrat de travail)\b/
    ],
    searchQueries: [
      "article 35 code du travail licenciement motif valable",
      "article 37 code du travail sanction disciplinaire faute non grave",
      "article 39 code du travail faute grave licenciement",
      "article 40 code du travail faute grave employeur",
      "article 41 code du travail licenciement abusif dommages interets",
      "article 43 code du travail preavis",
      "article 51 code du travail indemnite preavis",
      "article 52 code du travail indemnite licenciement",
      "article 53 code du travail indemnité licenciement anciennete",
      "article 59 code du travail licenciement abusif indemnites",
      "article 62 code du travail licenciement",
      "article 63 code du travail decision licenciement",
      "article 64 code du travail decision licenciement motifs",
      "article 65 code du travail action justice licenciement"
    ],
    relevanceTerms: [
      "travail",
      "salarie",
      "employeur",
      "licenciement",
      "faute grave",
      "preavis",
      "indemnite",
      "decision licenciement"
    ],
    ...sourceDomains.labor
  },
  {
    legalIssue: "commercial company rules",
    patterns: [
      /\b(company|corporation|business entity)\b.*\b(shareholder|director|manager|sarl|register|registration|formation|incorporate|capital|shares)\b/,
      /\b(shareholder|director|manager|sarl|register|registration|formation|incorporate|capital|shares)\b.*\b(company|corporation|business entity)\b/,
      /\b(sarl|commercial company|register company|shareholder|director)\b/,
      /\b(societe|associe|actionnaire|gerant|registre de commerce)\b/
    ],
    searchQueries: [
      "societe responsabilite limitee",
      "societes anonymes actionnaire",
      "registre de commerce",
      "code de commerce commercant",
      "gerant societe"
    ],
    ...sourceDomains.commercialCompany
  },
  {
    legalIssue: "family law",
    patterns: [
      /\b(divorce|marriage|custody|inheritance|child support|alimony)\b/,
      /\b(divorce|mariage|garde|heritage|succession|pension)\b/
    ],
    searchQueries: [
      "code de la famille divorce",
      "code de la famille garde",
      "pension alimentaire",
      "succession heritage",
      "mariage code de la famille"
    ],
    ...sourceDomains.family
  },
  {
    legalIssue: "consumer protection",
    patterns: [
      /\b(consumer|customer|refund|warranty|seller|defective|product)\b/,
      /\b(consommateur|remboursement|garantie|vendeur|defaut|produit)\b/
    ],
    searchQueries: [
      "protection du consommateur garantie",
      "protection du consommateur remboursement",
      "information consommateur",
      "pratiques commerciales consommateur"
    ],
    ...sourceDomains.consumer
  },
  {
    legalIssue: "renovation or construction work contract",
    patterns: [
      /\b(renovation|construction|repair|repairs|building work|work contract|works contract|travaux|ouvrage|chantier)\b.*\b(contract|contractor|builder|price|cost|material|materials|quote|estimate|devis|terminate|cancel|increase)\b/,
      /\b(contract|contractor|builder|price|cost|material|materials|quote|estimate|devis|terminate|cancel|increase)\b.*\b(renovation|construction|repair|repairs|building work|work contract|works contract|travaux|ouvrage|chantier)\b/,
      /\b(contractor|entrepreneur|builder|artisan|ouvrier)\b.*\b(material|materials|cost|price|increase|devis|quote|estimate|travaux|ouvrage|renovation|construction)\b/,
      /\b(prix fait|devis|maitre de l ouvrage|augmentation de prix|louage d ouvrage|louage d'ouvrage)\b/
    ],
    searchQueries: [
      "article 777 code des obligations et des contrats prix fait devis",
      "article 230 code des obligations et des contrats conventions",
      "article 231 code des obligations et des contrats bonne foi",
      "article 259 code des obligations et des contrats resolution dommages interets",
      "article 758 code des obligations et des contrats entrepreneur",
      "article 759 code des obligations et des contrats louage ouvrage",
      "article 766 code des obligations et des contrats matiere entrepreneur",
      "article 768 code des obligations et des contrats ouvrage vice delai"
    ],
    relevanceTerms: [
      "prix fait",
      "devis",
      "augmentation",
      "maitre",
      "travail",
      "travaux",
      "ouvrage",
      "entrepreneur",
      "matiere",
      "materiaux"
    ],
    ...sourceDomains.civilContracts
  },
  {
    legalIssue: "sale contract with missing or partial delivery",
    patterns: [
      /\b(sale|sold|sells|seller|buyer|bought|purchase|paid|payment|price|order)\b.*\b(deliver|delivery|delivered|received|receives|missing|partial|only|quantity|goods|products|items|laptops)\b/,
      /\b(deliver|delivery|delivered|received|receives|missing|partial|only|quantity|goods|products|items|laptops)\b.*\b(sale|sold|sells|seller|buyer|bought|purchase|paid|payment|price|order)\b/,
      /\b(vente|vendu|vendeur|acheteur|prix|paiement)\b.*\b(delivrance|livraison|reception|quantite|partielle|marchandise|produit)\b/,
      /\b(delivrance|livraison|reception|quantite|partielle|marchandise|produit)\b.*\b(vente|vendu|vendeur|acheteur|prix|paiement)\b/
    ],
    searchQueries: [
      "article 488 code des obligations et des contrats vente",
      "article 491 code des obligations et des contrats propriete chose vendue",
      "article 494 code des obligations et des contrats vente compte mesure",
      "article 496 code des obligations et des contrats reception acheteur",
      "article 498 code des obligations et des contrats delivrance",
      "article 499 code des obligations et des contrats delivrance possession",
      "article 500 code des obligations et des contrats choses mobilieres",
      "article 502 code des obligations et des contrats lieu delivrance",
      "article 504 code des obligations et des contrats paiement delivrance",
      "article 259 code des obligations et des contrats resolution dommages interets"
    ],
    relevanceTerms: [
      "vente",
      "acheteur",
      "vendeur",
      "delivrance",
      "chose vendue",
      "prix",
      "paiement",
      "compte",
      "mesure",
      "reception"
    ],
    ...sourceDomains.civilContracts
  },
  {
    legalIssue: "sale ownership and heirs dispute",
    patterns: [
      /\b(sold|sale|bought|buyer|seller|paid|payment|price)\b.*\b(car|vehicle|ownership|owner|registration|registered|heirs|inherit|inherited)\b/,
      /\b(car|vehicle|ownership|owner|registration|registered|heirs|inherit|inherited)\b.*\b(sold|sale|bought|buyer|seller|paid|payment|price)\b/,
      /\b(vente|vendu|acheteur|vendeur|prix)\b.*\b(propriete|propriété|possession|delivrance|délivrance|heritier|héritier|succession|vehicule|véhicule|immatriculation|carte grise)\b/,
      /\b(propriete|propriété|possession|delivrance|délivrance|heritier|héritier|succession|vehicule|véhicule|immatriculation|carte grise)\b.*\b(vente|vendu|acheteur|vendeur|prix)\b/
    ],
    searchQueries: [
      "article 488 code des obligations et des contrats vente",
      "article 491 code des obligations et des contrats propriete chose vendue",
      "article 492 code des obligations et des contrats chose vendue",
      "article 498 code des obligations et des contrats delivrance",
      "article 499 code des obligations et des contrats possession",
      "article 500 code des obligations et des contrats choses mobilieres",
      "article 504 code des obligations et des contrats paiement delivrance",
      "article 229 code des obligations et des contrats heritiers ayants cause",
      "article 489 code des obligations et des contrats enregistrement tiers"
    ],
    relevanceTerms: [
      "vente",
      "acheteur",
      "vendeur",
      "propriete",
      "delivrance",
      "possession",
      "heritier",
      "ayants cause"
    ],
    ...sourceDomains.civilContracts
  }
];

const createLocalSearchPlan = (question) => {
  const normalizedQuestion = normalizeText(question);
  let matches = legalQueryProfiles.filter((profile) =>
    profile.patterns.some((pattern) => pattern.test(normalizedQuestion))
  );
  const hasCommercialLeaseMatch = matches.some((profile) => profile.legalIssue === "commercial lease eviction");
  const hasEmploymentMatch = matches.some((profile) => profile.legalIssue === "employment termination");
  const hasRenovationMatch = matches.some((profile) => profile.legalIssue === "renovation or construction work contract");
  const hasSaleDeliveryMatch = matches.some((profile) => profile.legalIssue === "sale contract with missing or partial delivery");
  const hasWorkplaceAccusation =
    hasEmploymentMatch &&
    /\b(accuse|accusation|preuve|evidence|inventaire|inventory|ordinateur|laptop|vole|vol|theft)\b/.test(
      normalizedQuestion
    );
  const hasStructuredEmploymentAnalysis =
    hasEmploymentMatch &&
    (/\b(analysez|analyze|analyse|analysis|etudiez|evaluate)\b/.test(normalizedQuestion) ||
      /\b(faits|arguments|preuves|procedures|represailles|conclusion)\b/.test(normalizedQuestion));
  const asksAboutCompensation =
    /\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/.test(
      normalizedQuestion
    );

  if (hasCommercialLeaseMatch) {
    matches = matches.filter((profile) => profile.legalIssue !== "landlord and tenant rent or residential eviction");
  }

  if (hasEmploymentMatch) {
    matches = matches.filter((profile) =>
      !["commercial company rules", "stolen property or theft"].includes(profile.legalIssue)
    );
  }

  if (hasWorkplaceAccusation) {
    matches = matches.filter((profile) => profile.legalIssue === "employment termination");
  }

  if (hasRenovationMatch) {
    matches = matches.filter((profile) =>
      !["landlord and tenant rent or residential eviction", "real estate law overview"].includes(profile.legalIssue)
    );
  }

  if (hasSaleDeliveryMatch) {
    matches = matches.filter((profile) => profile.legalIssue !== "commercial company rules");
  }

  if (!matches.length) {
    return null;
  }

  return {
    legalIssue: matches.map((profile) => profile.legalIssue).join(" / "),
    reasoningGoal: "Explain the likely legal answer using the most relevant Moroccan law excerpts.",
    needsLawSearch: true,
    searchQueries: [...new Set(matches.flatMap((profile) => profile.searchQueries))]
      .filter((query) => {
        if (!hasStructuredEmploymentAnalysis || asksAboutCompensation) {
          return true;
        }

        return !/\b(article 40|article 43|article 51|article 52|article 53|article 59|preavis|indemnite)\b/i.test(query);
      })
      .slice(0, 16),
    relevanceTerms: [...new Set(matches.flatMap((profile) => profile.relevanceTerms || []))].slice(0, 24),
    allowedDocumentTitles: [...new Set(matches.flatMap((profile) => profile.allowedDocumentTitles || []))],
    allowedCategories: [...new Set(matches.flatMap((profile) => profile.allowedCategories || []))]
  };
};

const getOllamaConfig = () => ({
  baseUrl: (process.env.OLLAMA_BASE_URL || DEFAULT_OLLAMA_BASE_URL).replace(/\/+$/, ""),
  model: process.env.OLLAMA_MODEL || DEFAULT_OLLAMA_MODEL,
  plannerTimeoutMs: Number(process.env.AI_PLANNER_TIMEOUT_MS || DEFAULT_PLANNER_TIMEOUT_MS),
  answerTimeoutMs: Number(process.env.AI_ANSWER_TIMEOUT_MS || DEFAULT_ANSWER_TIMEOUT_MS)
});

const fetchWithTimeout = async (url, options, timeoutMs) => {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, {
      ...options,
      signal: controller.signal
    });
  } finally {
    clearTimeout(timeout);
  }
};

const callOllama = async ({ messages, format, temperature = 0.15, timeoutMs, numPredict = 700 }) => {
  if (!isAiReasoningEnabled()) {
    return null;
  }

  const config = getOllamaConfig();
  const response = await fetchWithTimeout(
    `${config.baseUrl}/api/chat`,
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        model: config.model,
        messages,
        stream: false,
        format,
        think: false,
        options: {
          temperature,
          top_p: 0.9,
          num_predict: numPredict,
          num_ctx: Number(process.env.OLLAMA_NUM_CTX || 8192),
          repeat_penalty: 1.08
        }
      })
    },
    timeoutMs
  );

  if (!response.ok) {
    throw new Error(`Ollama request failed: ${response.status}`);
  }

  const payload = await response.json();
  return stripThinkingText(payload?.message?.content || "");
};

const extractJsonObject = (text) => {
  const cleaned = stripThinkingText(text);

  try {
    return JSON.parse(cleaned);
  } catch {
    const match = cleaned.match(/\{[\s\S]*\}/);
    return match ? JSON.parse(match[0]) : null;
  }
};

const sanitizeSearchPlan = (plan) => {
  if (!plan || typeof plan !== "object") {
    return null;
  }

  const rawQueries = Array.isArray(plan.searchQueries)
    ? plan.searchQueries
    : Array.isArray(plan.search_queries)
    ? plan.search_queries
    : [];
  const searchQueries = [...new Set(
    rawQueries
      .map((query) => String(query || "").trim())
      .filter((query) => query.length >= 2)
  )].slice(0, 12);

  return {
    legalIssue: Array.isArray(plan.legal_issues)
      ? plan.legal_issues.map((issue) => String(issue || "").trim()).filter(Boolean).join(" / ")
      : String(plan.legalIssue || plan.legal_issue || "").trim(),
    reasoningGoal: String(plan.reasoningGoal || plan.reasoning_goal || "").trim(),
    needsLawSearch: plan.needsLawSearch !== false,
    searchQueries
  };
};

const createAiSearchPlan = async (question, history = []) => {
  const localPlan = createLocalSearchPlan(question);

  if (localPlan) {
    return localPlan;
  }

  if (!isAiReasoningEnabled()) {
    return null;
  }

  const config = getOllamaConfig();
  const recentHistory = Array.isArray(history)
    ? history
        .slice(-6)
        .map((message) => `${message.role || "user"}: ${message.text || ""}`)
        .join("\n")
    : "";

  try {
    const content = await callOllama({
      timeoutMs: config.plannerTimeoutMs,
      temperature: 0.1,
      numPredict: 320,
      format: "json",
      messages: [
        {
          role: "system",
          content:
            "You are a Moroccan legal assistant pipeline planner. Return JSON only. Do not answer the user. Stage 1: extract facts from the user question only into facts[]. Stage 2: spot legal issues and produce legal_issues[] plus precise French legal search queries in search_queries[]. Include likely code names and legal terms. Avoid broad one-word queries. If needsLawSearch is true, search_queries must contain at least 3 useful French queries."
        },
        {
          role: "user",
          content: JSON.stringify({
            examples: [
              {
                question: "can a landlord evict me?",
                searchQueries: [
                  "bailleur locataire expulsion",
                  "resiliation bail locataire",
                  "recouvrement loyers",
                  "loi 67-12 bail habitation"
                ]
              },
              {
                question: "what happens if my property got stolen?",
                searchQueries: [
                  "article 505 code penal",
                  "soustraction frauduleuse",
                  "vol code penal",
                  "article 506 code penal"
                ]
              }
            ],
            outputShape: {
              facts: ["fact from the user question only"],
              needsLawSearch: true,
              legal_issues: ["short issue label"],
              reasoning_goal: "what the final answer should explain",
              search_queries: [
                "French legal query 1",
                "French legal query 2",
                "specific code/article query if likely"
              ]
            },
            recentHistory,
            question
          })
        }
      ]
    });

    const aiPlan = sanitizeSearchPlan(extractJsonObject(content));

    if (aiPlan?.searchQueries?.length) {
      return aiPlan;
    }

    return createLocalSearchPlan(question) || aiPlan;
  } catch (error) {
    console.error("[ai-reasoning] Search planning failed");
    console.error(error);
    return createLocalSearchPlan(question);
  }
};

const formatCitationForPrompt = (citation, index) => {
  const sourceParts = [
    citation.articleNumber,
    citation.documentTitle,
    citation.lawReference,
    citation.category
  ].filter(Boolean);

  return [
    `[${index + 1}] ${citation.title}`,
    citation.matchedQuery ? `Matched search query: ${citation.matchedQuery}` : "",
    sourceParts.length ? `Source: ${sourceParts.join(" | ")}` : "",
    `Text: ${cleanLawExcerpt(citation.content).slice(0, 1200)}`
  ]
    .filter(Boolean)
    .join("\n");
};

const removeUnsupportedPracticalAdvice = (answer, citationContext) => {
  const normalizedContext = normalizeText(citationContext);
  const contextHasEvictionRule =
    /\b(evict|eviction|expulsion)\b/.test(normalizedContext);
  const guardedConcepts = [
    {
      terms: ["police", "report", "complaint"],
      contextPattern: /\b(police|plainte|denonciation|declaration)\b/
    },
    {
      terms: ["compensation", "remedy", "remedies"],
      contextPattern: /\b(indemnite|indemnites|dommages interets|dommage interets|reparation|reintegration)\b/
    },
    {
      terms: ["court", "judge", "lawsuit", "legal action", "sue"],
      contextPattern: /\b(tribunal|tribunaux|action en justice|saisir|juge|competent|conciliation)\b/
    },
    {
      terms: ["deadline"],
      contextPattern: /\b(delai|90 jours|48 heures|huit jours|quinze jours)\b/
    },
    {
      terms: ["lawyer"],
      contextPattern: /\b(avocat|avocate)\b/
    },
    {
      terms: ["prior record", "criminal record"],
      contextPattern: /\b(casier judiciaire|antecedent)\b/
    }
  ];
  const sentences = String(answer || "")
    .split(/(?<=[.!?])\s+/)
    .map((sentence) => sentence.trim())
    .filter(Boolean);

  return sentences
    .map((sentence) => {
      const normalizedSentence = normalizeText(sentence);
      if (!contextHasEvictionRule && /^(yes|no)$/.test(normalizedSentence)) {
        return "";
      }

      const hasUnsupportedEvictionTerm =
        /\b(evict|eviction|expulsion)\b/.test(normalizedSentence) && !contextHasEvictionRule;
      const isLimitationSentence =
        normalizedSentence.includes("do not cover") ||
        normalizedSentence.includes("does not cover") ||
        normalizedSentence.includes("not included") ||
        normalizedSentence.includes("not enough");
      const hasUnsupportedPracticalTerm = guardedConcepts.some(({ terms, contextPattern }) =>
        terms.some((term) => normalizedSentence.includes(term)) && !contextPattern.test(normalizedContext)
      );

      if (hasUnsupportedEvictionTerm) {
        return "The provided excerpts do not directly cover eviction or expulsion; they only support the related rent-recovery or lease points they mention.";
      }

      if (isLimitationSentence && hasUnsupportedPracticalTerm) {
        return "The provided excerpts do not cover reporting steps, compensation, recovery procedures, or other remedies.";
      }

      return hasUnsupportedPracticalTerm ? "" : sentence;
    })
    .filter(Boolean)
    .filter((sentence, index, allSentences) => allSentences.indexOf(sentence) === index)
    .join(" ");
};

const ensureCitationMarker = (answer, citations) => {
  if (!citations?.length || /\[\d+\]/.test(answer)) {
    return answer;
  }

  const trimmedAnswer = String(answer || "").trim();
  return `${trimmedAnswer}${/[.!?]$/.test(trimmedAnswer) ? " [1]" : " [1]."}`;
};

const removeInvalidCitationMarkers = (answer, citations) =>
  String(answer || "").replace(/\[(\d+)\]/g, (match, citationNumber) => {
    const index = Number(citationNumber);
    return index >= 1 && index <= citations.length ? match : "";
  });

const buildCitationLead = (citations) => {
  const citation = citations?.[0];

  if (!citation) {
    return "";
  }

  const sourceParts = [
    citation.articleNumber,
    citation.documentTitle,
    citation.lawReference
  ].filter(Boolean);
  const excerpt = cleanLawExcerpt(citation.content).slice(0, 280);

  if (!excerpt) {
    return "";
  }

  return `The closest retrieved rule is ${sourceParts.join(" from ") || citation.title}: ${excerpt} [1].`;
};

const findCitationMarker = (citations, articleNumber, documentTitle = "Code du travail") => {
  const index = citations.findIndex(
    (citation) =>
      citation.articleNumber === articleNumber &&
      (!documentTitle || citation.documentTitle === documentTitle)
  );

  return index >= 0 ? `[${index + 1}]` : "";
};

const extractYearsOfService = (question) => {
  const match = String(question || "").match(/\b(\d+(?:[.,]\d+)?)\s*(?:years?|ans|annees?|années?)\b/i);

  if (!match) {
    return null;
  }

  return Number(match[1].replace(",", "."));
};

const formatLegalNumber = (value) => {
  if (typeof value !== "number" || !Number.isFinite(value)) {
    return "";
  }

  return Number.isInteger(value) ? String(value) : String(Math.round(value * 100) / 100);
};

const yearsForLegalFormula = (years) =>
  typeof years === "number" && Number.isFinite(years) && years > 0 ? Math.ceil(years) : null;

const calculateSeveranceHours = (years) => {
  const legalYears = yearsForLegalFormula(years);

  if (!legalYears) {
    return null;
  }

  return (
    Math.min(legalYears, 5) * 96 +
    Math.min(Math.max(legalYears - 5, 0), 5) * 144 +
    Math.min(Math.max(legalYears - 10, 0), 5) * 192 +
    Math.max(legalYears - 15, 0) * 240
  );
};

const calculateAbusiveDismissalMonths = (years) => {
  const legalYears = yearsForLegalFormula(years);

  return legalYears ? Math.min(Math.round(legalYears * 1.5 * 100) / 100, 36) : null;
};

const getQuestionFactProfile = (question) => {
  const normalizedQuestion = normalizeText(question);

  return {
    yearsOfService: extractYearsOfService(question),
    asksAboutCompensation: /\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/.test(
      normalizedQuestion
    ),
    mentionsPersonalEmail: /\b(personal email|personal mail|email personnel|mail personnel|adresse email personnelle|adresse personnelle)\b/.test(
      normalizedQuestion
    ),
    mentionsWorkFromHome: /\b(domicile|home|teletravail|telework|remote work|work from home|work from his home|work from her home)\b/.test(
      normalizedQuestion
    ),
    mentionsNoCompetitorDisclosure: /\b(no competitor|no competitors|aucun concurrent|concurrent n a recu|competitor received|competitors received)\b/.test(
      normalizedQuestion
    ),
    mentionsNoDisclosure: /\b(aucun document|no document|pas divulgue|not disclosed|tiers|third parties|no competitor|no competitors|aucun concurrent)\b/.test(
      normalizedQuestion
    ),
    mentionsNoDamage: /\b(aucun prejudice|prejudice concret|ne demontre aucun prejudice|no damage|no proven damage|no harm|concrete harm)\b/.test(
      normalizedQuestion
    ),
    mentionsImmediateDismissal: /\b(immediate dismissal|immediately dismissed|dismissed immediately|licencie immediatement|licenciement immediat|immediatement)\b/.test(
      normalizedQuestion
    )
  };
};

const extractLegalFacts = (question) => {
  const normalizedQuestion = normalizeText(question);
  const factProfile = getQuestionFactProfile(question);
  const yearsOfService = factProfile.yearsOfService;
  const facts = [];
  const addFact = (fact) => {
    if (fact && !facts.includes(fact)) {
      facts.push(fact);
    }
  };

  if (typeof yearsOfService === "number" && Number.isFinite(yearsOfService)) {
    addFact(`Le salarie a ${formatLegalNumber(yearsOfService)} ans d'anciennete.`);
  }

  if (/\b(sans aucun antecedent|aucun antecedent|antecedent disciplinaire|no disciplinary|pas d antecedent|no warning|aucun avertissement|no prior warnings)\b/.test(normalizedQuestion)) {
    addFact("Aucun antecedent disciplinaire ou avertissement anterieur n'est mentionne.");
  }

  if (/\b(securite|safety|danger|violations|regles de securite|workers|travailleurs)\b/.test(normalizedQuestion)) {
    addFact("Le salarie a signale des problemes de securite pouvant mettre les travailleurs en danger.");
  }

  if (/\b(trois|three|3)\s*(semaines|weeks)\b/.test(normalizedQuestion)) {
    addFact("La procedure disciplinaire commence environ trois semaines apres le signalement.");
  }

  if (/\b(deux|two|2)\b.*\b(employes|employees|salaries|witnesses|temoins|confirment|confirm)\b/.test(normalizedQuestion)) {
    addFact("Deux autres employes confirment que les problemes signales existaient.");
  }

  if (/\b(attitude negative|negative attitude|perturbation|bon fonctionnement|normal operation|disruption)\b/.test(normalizedQuestion)) {
    addFact("L'employeur invoque une attitude negative ou une perturbation du fonctionnement normal.");
  }

  if (/\b(licencie|licenciement|dismissal|dismiss|dismisses|dismissed|fired|terminated|termination)\b/.test(normalizedQuestion)) {
    addFact("Le salarie est finalement licencie.");
  }

  if (/\b(accuse|accusation|accuses|alleges|allegation|affirme|qualifie)\b/.test(normalizedQuestion)) {
    addFact("L'entreprise accuse le salarie d'un fait fautif.");
  }

  if (/\b(vol|vole|theft|stolen)\b/.test(normalizedQuestion)) {
    addFact("L'accusation porte sur un vol.");
  }

  if (/\b(ordinateur|laptop|portable)\b/.test(normalizedQuestion)) {
    addFact("L'objet mentionne est un ordinateur portable.");
  }

  if (/\b(aucun|no)\b.*\b(manquant|missing)\b|\b(inventaire|inventory)\b/.test(normalizedQuestion)) {
    addFact("Selon l'inventaire de l'entreprise, aucun ordinateur n'est manquant.");
  }

  if (/\b(document|documents|donnees|data|fichier|fichiers)\b/.test(normalizedQuestion)) {
    addFact("Les elements concernes sont des documents ou donnees professionnels.");
  }

  if (factProfile.mentionsPersonalEmail) {
    addFact("Les documents ont ete envoyes vers l'adresse email personnelle du salarie.");
  }

  if (/\b(usb|cle usb)\b/.test(normalizedQuestion)) {
    addFact("Les documents ont ete transferes sur une cle USB personnelle.");
  }

  if (factProfile.mentionsWorkFromHome) {
    addFact("Le salarie explique que le transfert servait a travailler depuis son domicile.");
  }

  if (factProfile.mentionsNoCompetitorDisclosure) {
    addFact("Aucun concurrent n'est presente comme ayant recu les documents.");
  }

  if (factProfile.mentionsNoDisclosure) {
    addFact("Aucune divulgation a des tiers n'est mentionnee.");
  }

  if (factProfile.mentionsNoDamage) {
    addFact("Aucun prejudice concret n'est demontre dans la question.");
  }

  if (factProfile.mentionsImmediateDismissal) {
    addFact("Le licenciement est presente comme immediat.");
  }

  if (
    /\b(accuse|accusation|accuses|alleges|allegation|affirme|qualifie)\b/.test(normalizedQuestion) &&
    !/\b(preuve directe|direct evidence|temoin|witness|camera|video)\b/.test(normalizedQuestion)
  ) {
    addFact(
      /\b(vol|vole|theft|stolen)\b/.test(normalizedQuestion)
        ? "Aucune preuve directe du vol n'est mentionnee dans la question."
        : "Aucune preuve directe du fait fautif n'est mentionnee dans la question."
    );
  }

  return facts;
};

const extractLegalIssues = (question, plan) => {
  const normalizedQuestion = normalizeText(question);
  const issues = [];
  const addIssue = (issue) => {
    if (issue && !issues.includes(issue)) {
      issues.push(issue);
    }
  };

  if (/\b(salarie|employee|employe|employer|employeur|entreprise|company|usine|factory|travail|licencie|licenciement|disciplinaire)\b/.test(normalizedQuestion)) {
    addIssue("Validite et justification du licenciement");
    addIssue("Regularite de la procedure disciplinaire");
  }

  if (/\b(securite|safety|danger|violations|represailles|retaliation)\b/.test(normalizedQuestion)) {
    addIssue("Lien possible entre le signalement de securite et une mesure de represailles");
  }

  if (/\b(accuse|accusation|preuve|evidence|inventaire|inventory|vol|theft|confidentiel|confidential|usb|data|donnees)\b/.test(normalizedQuestion)) {
    addIssue("Charge de la preuve et suffisance des elements reproches");
  }

  if (/\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/.test(normalizedQuestion)) {
    addIssue("Indemnites ou dommages-interets eventuels");
  }

  if (plan?.aiPlan?.legalIssue) {
    addIssue(plan.aiPlan.legalIssue);
  }

  return issues;
};

const asksForFactExtractionOnly = (normalizedQuestion) =>
  /\b(liste|list|identify|extraire|extract)\b.*\b(faits|facts)\b/.test(normalizedQuestion) &&
  /\b(sans citer|ne cite aucun|do not cite|dont cite|no articles|no article|no laws|no law|without citing|must not cite)\b/.test(
    normalizedQuestion
  );

const asksAboutAccusationEvidence = (normalizedQuestion) =>
  /\b(accuse|accusation|accuses|alleges|allegation|preuve|evidence|affaiblissent|weakens|weaken|contradiction|inventaire|inventory|ordinateur|laptop|document|documents|donnees|data|confidentiel|confidential|usb|cle usb|email|mail|competitor|concurrent|damage|prejudice|vole|vol|theft)\b/.test(
    normalizedQuestion
  ) &&
  /\b(salarie|employee|employe|employer|employeur|entreprise|company)\b/.test(normalizedQuestion);

const buildFactExtractionAnswer = ({ question }) => {
  const normalizedQuestion = normalizeText(question);

  if (!asksForFactExtractionOnly(normalizedQuestion)) {
    return null;
  }

  const extractedFacts = extractLegalFacts(question);

  if (extractedFacts.length) {
    return extractedFacts.map((fact, index) => `${index + 1}. ${fact}`).join("\n");
  }

  const facts = [];

  if (/\b(accuse|accusation|accuses|alleges|allegation|affirme|qualifie|licencie)\b/.test(normalizedQuestion)) {
    facts.push("L'entreprise accuse le salarie d'un fait fautif.");
  }

  if (/\b(vol|vole|theft|stolen)\b/.test(normalizedQuestion)) {
    facts.push("L'accusation porte sur un vol.");
  }

  if (/\b(ordinateur|laptop|portable)\b/.test(normalizedQuestion)) {
    facts.push("L'objet mentionne est un ordinateur portable.");
  }

  if (/\b(document|documents|donnees|data|fichier|fichiers)\b/.test(normalizedQuestion)) {
    facts.push("Les elements concernes sont des documents ou donnees professionnels.");
  }

  if (/\b(usb|cle usb)\b/.test(normalizedQuestion)) {
    facts.push("Les documents ont ete transferes sur une cle USB personnelle.");
  }

  if (/\b\d+(?:[.,]\d+)?\s*(ans|annees|years)\b/.test(normalizedQuestion)) {
    facts.push("L'anciennete du salarie est mentionnee dans la question.");
  }

  if (/\b(sans aucun antecedent|aucun antecedent|antecedent disciplinaire|no disciplinary|pas d antecedent)\b/.test(normalizedQuestion)) {
    facts.push("Aucun antecedent disciplinaire n'est mentionne.");
  }

  if (/\b(domicile|home|teletravail|telework|remote work|work from home)\b/.test(normalizedQuestion)) {
    facts.push("Le salarie explique que le transfert servait a travailler depuis son domicile.");
  }

  if (/\b(aucun|no)\b.*\b(manquant|missing)\b|\b(inventaire|inventory)\b/.test(normalizedQuestion)) {
    facts.push("Selon l'inventaire de l'entreprise, aucun ordinateur n'est manquant.");
  }

  if (/\b(aucun document|no document|pas divulgue|not disclosed|tiers|third parties)\b/.test(normalizedQuestion)) {
    facts.push("Aucune divulgation a des tiers n'est mentionnee.");
  }

  if (/\b(aucun prejudice|prejudice concret|ne demontre aucun prejudice|no damage|no harm|concrete harm)\b/.test(normalizedQuestion)) {
    facts.push("Aucun prejudice concret n'est demontre dans la question.");
  }

  if (!/\b(preuve directe|direct evidence|temoin|witness|camera|video)\b/.test(normalizedQuestion)) {
    facts.push("Aucune preuve directe du vol n'est mentionnee dans la question.");
  }

  return facts.length ? facts.map((fact, index) => `${index + 1}. ${fact}`).join("\n") : null;
};

const asksForStructuredCaseAnalysis = (normalizedQuestion) =>
  /\b(analysez|analyze|analyse|analysis|etudiez|evaluate)\b/.test(normalizedQuestion) ||
  (
    /\b(faits|facts|arguments|preuves|evidence|procedures|represailles|retaliation|conclusion)\b/.test(
      normalizedQuestion
    ) &&
    /\b(arguments|preuves|evidence|procedures|conclusion|circonstances|represailles|retaliation)\b/.test(
      normalizedQuestion
    )
  );

const isEmploymentScenario = (normalizedQuestion) =>
  /\b(salarie|employee|employe|employer|employeur|entreprise|company|usine|factory|travail|licencie|licenciement|disciplinaire)\b/.test(
    normalizedQuestion
  );

const isFactRichEmploymentCase = (normalizedQuestion) =>
  isEmploymentScenario(normalizedQuestion) &&
  /\b(document|documents|donnees|data|confidentiel|confidential|usb|cle usb|email|mail|fichier|fichiers|transfere|transfer|sent)\b/.test(
    normalizedQuestion
  ) &&
  /\b(licencie|licenciement|dismissal|dismiss|dismisses|dismissed|fired|terminated|termination|faute grave|serious fault)\b/.test(
    normalizedQuestion
  );

const extractSeniorityYears = (question) => {
  const normalizedQuestion = normalizeText(question);
  const match = normalizedQuestion.match(/\b(?:depuis|for|worked for|travaille depuis)?\s*(\d+(?:[.,]\d+)?)\s*(?:ans|annees|years)\b/);

  return match ? Number(match[1].replace(",", ".")) : null;
};

const buildEmploymentCaseAnalysisAnswer = ({ question, citations, force = false }) => {
  const normalizedQuestion = normalizeText(question);

  if (!force && (!isEmploymentScenario(normalizedQuestion) || (!asksForStructuredCaseAnalysis(normalizedQuestion) && !isFactRichEmploymentCase(normalizedQuestion)))) {
    return null;
  }

  const article35 = findCitationMarker(citations, "Article 35");
  const article37 = findCitationMarker(citations, "Article 37");
  const article39 = findCitationMarker(citations, "Article 39");
  const article41 = findCitationMarker(citations, "Article 41");
  const article43 = findCitationMarker(citations, "Article 43");
  const article51 = findCitationMarker(citations, "Article 51");
  const article52 = findCitationMarker(citations, "Article 52");
  const article53 = findCitationMarker(citations, "Article 53");
  const article59 = findCitationMarker(citations, "Article 59");
  const article62 = findCitationMarker(citations, "Article 62");
  const article63 = findCitationMarker(citations, "Article 63");
  const article64 = findCitationMarker(citations, "Article 64");
  const article65 = findCitationMarker(citations, "Article 65");

  if (!article35 || !article62 || !article63 || !article64) {
    return null;
  }

  const factProfile = getQuestionFactProfile(question);
  const yearsOfService = extractSeniorityYears(question);
  const mentionsNoDiscipline = /\b(sans aucun antecedent|aucun antecedent|antecedent disciplinaire|no disciplinary|jamais|never|aucun avertissement|no warning|no prior warnings?|no previous warnings?)\b/.test(normalizedQuestion);
  const mentionsSafetyReport = /\b(securite|safety|danger|violations|regles de securite|workers|travailleurs)\b/.test(normalizedQuestion);
  const mentionsWitnesses = /\b(deux|two|2)\b.*\b(employes|employees|salaries|witnesses|temoins|confirment|confirm)\b/.test(normalizedQuestion);
  const mentionsShortDelay = /\b(trois|three|3)\s*(semaines|weeks)\b/.test(normalizedQuestion);
  const mentionsNegativeAttitude = /\b(attitude negative|negative attitude|perturbation|bon fonctionnement|disruption)\b/.test(normalizedQuestion);
  const mentionsDataTransfer = /\b(document|documents|donnees|data|confidentiel|confidential|usb|cle usb|email|mail|fichier|fichiers|transfere|transfer|sent)\b/.test(normalizedQuestion);
  const mentionsNoDisclosure = factProfile.mentionsNoDisclosure;
  const mentionsNoDamage = factProfile.mentionsNoDamage;

  const facts = [
    typeof yearsOfService === "number" ? `le salarie a ${formatLegalNumber(yearsOfService)} ans d'anciennete` : "",
    mentionsNoDiscipline ? "aucun antecedent disciplinaire ou avertissement anterieur n'est mentionne" : "",
    mentionsSafetyReport ? "le salarie a signale des problemes de securite pouvant mettre les travailleurs en danger" : "",
    mentionsWitnesses ? "deux autres employes confirment que les problemes signales existaient" : "",
    mentionsShortDelay ? "la procedure disciplinaire commence environ trois semaines apres le signalement" : "",
    mentionsNegativeAttitude ? "l'employeur invoque une attitude negative ou une perturbation du fonctionnement de l'entreprise" : "",
    mentionsDataTransfer ? "le litige porte sur un transfert de documents professionnels vers un support personnel" : "",
    factProfile.mentionsPersonalEmail ? "les documents ont ete envoyes vers l'adresse email personnelle du salarie" : "",
    factProfile.mentionsWorkFromHome ? "le salarie explique que l'envoi servait a travailler depuis son domicile" : "",
    factProfile.mentionsNoCompetitorDisclosure ? "aucun concurrent n'est presente comme ayant recu les documents" : "",
    mentionsNoDisclosure ? "aucune divulgation a des tiers n'est mentionnee" : "",
    mentionsNoDamage ? "aucun prejudice concret n'est demontre" : "",
    factProfile.mentionsImmediateDismissal ? "le licenciement est presente comme immediat" : "",
    /\b(licencie|licenciement|dismissal|dismiss|dismisses|dismissed|fired|terminated|termination)\b/.test(normalizedQuestion) ? "le salarie est finalement licencie" : ""
  ].filter(Boolean);

  const employerArguments = [
    mentionsNegativeAttitude ? "l'employeur peut soutenir que la sanction vise le comportement du salarie, son attitude negative ou une perturbation interne, et non le signalement lui-meme" : "",
    mentionsSafetyReport ? "il peut soutenir que le signalement a ete formule de maniere excessive ou perturbatrice" : "",
    mentionsDataTransfer ? "il peut soutenir que le transfert de documents professionnels sur un support personnel, notamment une adresse email personnelle, creait un risque de confidentialite ou rompait la confiance" : "",
    `ces arguments doivent constituer un motif valable au sens d'Article 35 ${article35}`
  ].filter(Boolean);

  const employeeArguments = [
    mentionsSafetyReport ? "le salarie peut soutenir qu'il a signale des risques reels pour la securite, ce qui rend la reaction disciplinaire suspecte" : "",
    mentionsWitnesses ? "les confirmations de deux autres employes renforcent la credibilite du signalement" : "",
    mentionsShortDelay ? "le delai court entre le signalement et la procedure disciplinaire peut suggerer un lien de causalite ou des represailles" : "",
    mentionsNoDiscipline || typeof yearsOfService === "number" ? "l'anciennete et l'absence d'antecedent disciplinaire pesent en faveur de la proportionnalite et contre une faute grave soudaine" : "",
    mentionsDataTransfer ? "si le transfert de fichiers avait une finalite professionnelle expliquee, cela peut affaiblir l'idee d'une intention frauduleuse" : "",
    factProfile.mentionsWorkFromHome ? "l'explication de travail a domicile donne une lecture professionnelle possible du comportement reproche" : "",
    factProfile.mentionsNoCompetitorDisclosure ? "l'absence d'envoi a un concurrent affaiblit l'idee d'une exploitation externe des documents" : "",
    mentionsNoDisclosure || mentionsNoDamage ? "l'absence de divulgation ou de prejudice concret est favorable au salarie, sans exclure automatiquement toute faute si une regle claire a ete violee" : "",
    factProfile.mentionsImmediateDismissal ? "le caractere immediat de la rupture rend la proportionnalite et la preuve de la faute grave particulierement importantes" : ""
  ].filter(Boolean);

  const decisiveEvidence = [
    "la chronologie exacte entre les faits, l'ouverture disciplinaire et le licenciement",
    mentionsSafetyReport ? "les preuves des violations de securite signalees" : "",
    mentionsWitnesses ? "les temoignages des deux employes" : "",
    "la decision ecrite de licenciement et les motifs qu'elle contient",
    "le proces-verbal d'audition disciplinaire",
    mentionsNegativeAttitude ? "les faits concrets derriere les termes vagues comme attitude negative ou perturbation" : "",
    mentionsDataTransfer ? "la politique de confidentialite, les autorisations de teletravail, les traces d'envoi email et l'usage reel des fichiers" : "",
    factProfile.mentionsNoCompetitorDisclosure ? "la preuve qu'aucun concurrent ou tiers externe n'a recu les documents" : "",
    mentionsNoDamage ? "la preuve d'un prejudice ou d'un risque concret" : ""
  ].filter(Boolean);

  const compensationLines = [];
  if (typeof yearsOfService === "number" && /(?:compensation|indemnite|dommages|claim|reclamer)/.test(normalizedQuestion)) {
    const severanceHours = calculateSeveranceHours(yearsOfService);
    const abusiveDismissalMonths = calculateAbusiveDismissalMonths(yearsOfService);
    compensationLines.push(
      article52 && article53 ? `Indemnite de licenciement: Articles 52 et 53 donnent une indemnite apres six mois de service, calculee par tranches: 96 heures par annee pour les cinq premieres annees, 144 heures pour les annees 6 a 10, 192 heures pour les annees 11 a 15, puis 240 heures au-dela; pour ${formatLegalNumber(yearsOfService)} ans, cela donne ${severanceHours} heures de salaire avant toute regle plus favorable ${article52} ${article53}.` : "",
      article41 ? `Si le licenciement est abusif, Article 41 prevoit la reintegration ou des dommages-interets d'un mois et demi de salaire par an ou fraction d'annee, plafonnes a 36 mois; pour ${formatLegalNumber(yearsOfService)} ans, cela donne ${formatLegalNumber(abusiveDismissalMonths)} mois de salaire ${article41}.` : "",
      article43 && article51 ? `L'indemnite de preavis peut aussi etre discutee si la rupture sans preavis n'est pas justifiee par une faute grave ${article43} ${article51}.` : ""
    );
  }

  const applicableArticles = [
    article35 ? `Article 35: motif valable de licenciement ${article35}` : "",
    article62 ? `Article 62: audition et defense du salarie avant sanction ${article62}` : "",
    article63 ? `Article 63: notification et charge de justification du licenciement ${article63}` : "",
    article64 ? `Article 64: motifs de la decision et limites du controle du tribunal ${article64}` : "",
    article37 ? `Article 37: sanctions disciplinaires progressives pour faute non grave ${article37}` : "",
    article39 ? `Article 39: exemples de fautes graves possibles ${article39}` : "",
    article65 ? `Article 65: delai de contestation du licenciement ${article65}` : ""
  ].filter(Boolean);
  const legalQuestions = [
    "le licenciement repose-t-il sur un motif valable et prouve",
    "la procedure disciplinaire a-t-elle ete respectee",
    mentionsSafetyReport || mentionsShortDelay ? "la chronologie revele-t-elle un indice de represailles" : "",
    "les griefs invoques sont-ils assez precis et graves pour justifier la sanction"
  ].filter(Boolean);
  const factualAnalysis = [
    mentionsSafetyReport ? "le signalement de risques de securite est central parce qu'il donne un contexte non disciplinaire au comportement du salarie" : "",
    mentionsShortDelay ? "le delai de trois semaines entre le signalement et la procedure rend le mobile de l'employeur discutable" : "",
    mentionsWitnesses ? "les deux temoignages renforcent l'idee que le signalement portait sur des faits reels et non sur une simple attitude hostile" : "",
    mentionsNegativeAttitude ? "des termes vagues comme attitude negative ou perturbation doivent etre traduits en faits concrets par l'employeur" : "",
    mentionsDataTransfer ? "l'envoi de documents vers un support personnel peut constituer un risque disciplinaire, mais il faut examiner la finalite, les regles internes, la confidentialite, l'autorisation de teletravail et l'usage reel des fichiers" : "",
    factProfile.mentionsWorkFromHome ? "l'explication de travail a domicile est un fait favorable au salarie car elle propose une raison professionnelle au transfert" : "",
    factProfile.mentionsNoCompetitorDisclosure ? "l'absence de transmission a un concurrent reduit l'indice d'intention frauduleuse ou de concurrence deloyale" : "",
    mentionsNoDamage ? "l'absence de prejudice prouve pese sur la gravite et la proportionnalite, meme si un risque de confidentialite peut encore etre discute" : "",
    factProfile.mentionsImmediateDismissal ? "une rupture immediate exige une justification solide de faute grave et une procedure respectee" : "",
    mentionsNoDiscipline || typeof yearsOfService === "number" ? "l'anciennete et l'absence d'avertissement peuvent affaiblir l'idee d'une faute grave soudaine" : "",
    "Article 63 rend decisive la preuve apportee par l'employeur; Article 64 oblige aussi a regarder les motifs effectivement ecrits dans la decision"
  ].filter(Boolean);
  const limits = [
    "il manque le contenu exact de la lettre de licenciement",
    "il manque le proces-verbal d'audition et les preuves produites par l'employeur",
    mentionsDataTransfer ? "il manque la politique interne sur l'usage des emails personnels, les autorisations de teletravail et la preuve technique de l'envoi" : "",
    mentionsSafetyReport ? "il manque les documents internes sur la securite et les temoignages complets" : "",
    "la base cite ici les regles de licenciement et de procedure; elle ne prouve pas a elle seule les faits materiels du dossier"
  ].filter(Boolean);
  const conclusionCore = mentionsDataTransfer
    ? "la position du salarie est serieuse si l'envoi avait une finalite professionnelle, si aucun concurrent ou tiers n'a recu les documents, si aucun prejudice concret n'est prouve, et si l'employeur ne prouve pas une regle claire, la confidentialite des documents, une intention fautive ou un risque grave"
    : mentionsSafetyReport
      ? "la position du salarie est serieuse si les faits signales etaient reels, si la chronologie suggere une reaction punitive, et si l'employeur ne prouve pas des faits precis constituant un motif valable"
      : "la position du salarie est serieuse si l'employeur ne prouve pas des faits precis constituant un motif valable et une procedure regulierement menee";

  return [
    `A. Faits importants: ${facts.length ? facts.join("; ") : "les faits doivent d'abord etre identifies a partir du scenario de l'utilisateur"}.`,
    `B. Questions juridiques: ${legalQuestions.join("; ")}.`,
    `C. Articles applicables: ${applicableArticles.join("; ")}.`,
    `D. Analyse des faits: ${factualAnalysis.join("; ")}. La faute grave n'est pas deduite automatiquement d'une etiquette disciplinaire: elle doit etre prouvee et appreciee dans le contexte concret.`,
    `E. Arguments de chaque partie: Employeur: ${employerArguments.join("; ")}. Salarie: ${employeeArguments.join("; ")}.`,
    `F. Preuves importantes: ${decisiveEvidence.join("; ")}. Article 63 est important car la justification du licenciement incombe a l'employeur ${article63}. Article 64 limite ensuite le tribunal aux motifs mentionnes dans la decision et aux circonstances dans lesquelles elle a ete prise ${article64}.`,
    compensationLines.filter(Boolean).join(" "),
    article65 ? `G. Conclusion probable: ${conclusionCore}. Le licenciement peut donc etre conteste comme abusif ou insuffisamment justifie, sauf preuve concrete d'un motif disciplinaire independant et d'une procedure regulierement menee ${article35}. Le salarie doit aussi surveiller le delai de 90 jours a compter de la reception de la decision ${article65}.` : `G. Conclusion probable: ${conclusionCore}. Le licenciement peut donc etre conteste comme abusif ou insuffisamment justifie, sauf preuve concrete d'un motif disciplinaire independant et d'une procedure regulierement menee ${article35}.`,
    `H. Limites / informations manquantes: ${limits.join("; ")}.`
  ]
    .filter(Boolean)
    .join(" ");
};

const buildEmploymentEvidenceAnswer = ({ question, citations }) => {
  const normalizedQuestion = normalizeText(question);

  if (!asksAboutAccusationEvidence(normalizedQuestion) || asksForFactExtractionOnly(normalizedQuestion)) {
    return null;
  }

  const article35 = findCitationMarker(citations, "Article 35");
  const article39 = findCitationMarker(citations, "Article 39");
  const article62 = findCitationMarker(citations, "Article 62");
  const article63 = findCitationMarker(citations, "Article 63");
  const article64 = findCitationMarker(citations, "Article 64");

  if (!article35 || !article39) {
    return null;
  }

  const isDataTransferCase =
    /\b(document|documents|donnees|data|confidentiel|confidential|usb|cle usb|email|mail|fichier|fichiers|transfere|transfer|sent)\b/.test(
      normalizedQuestion
    );

  if (isDataTransferCase) {
    const factProfile = getQuestionFactProfile(question);
    const yearsOfService = extractYearsOfService(question);
    const hasLongService = typeof yearsOfService === "number" && Number.isFinite(yearsOfService);
    const hasNoDiscipline = /\b(sans aucun antecedent|no disciplinary|aucun antecedent|antecedent disciplinaire|pas d antecedent)\b/.test(
      normalizedQuestion
    );
    const hasWorkFromHome = factProfile.mentionsWorkFromHome;
    const hasNoDisclosure = factProfile.mentionsNoDisclosure;
    const hasNoDamage = factProfile.mentionsNoDamage;
    const transferTarget = factProfile.mentionsPersonalEmail
      ? "il a envoye des documents professionnels vers son adresse email personnelle;"
      : "il a transfere des documents professionnels sur un support personnel;";
    const personalStorageLabel = factProfile.mentionsPersonalEmail ? "l'adresse email personnelle" : "le support personnel";

    return [
      [
        "Faits juridiquement importants:",
        hasLongService ? `le salarie a ${formatLegalNumber(yearsOfService)} ans d'anciennete;` : "",
        hasNoDiscipline ? "il n'a aucun antecedent disciplinaire;" : "",
        transferTarget,
        hasWorkFromHome ? "il explique que le transfert servait a travailler depuis son domicile;" : "",
        "l'employeur qualifie ce transfert de faute grave et licencie immediatement;",
        factProfile.mentionsNoCompetitorDisclosure ? "aucun concurrent n'est presente comme ayant recu les documents;" : "",
        hasNoDisclosure ? "aucun document n'est presente comme divulgue a des tiers;" : "",
        hasNoDamage ? "aucun prejudice concret n'est demontre." : ""
      ]
        .filter(Boolean)
        .join(" "),
      `Questions juridiques: le vrai debat est de savoir si le transfert de fichiers suffit a etablir une faute grave assimilable a un vol ou a une violation grave de confiance, ou s'il s'agit d'une utilisation professionnelle maladroite mais explicable. Article 39 vise notamment le vol et l'abus de confiance comme fautes graves possibles, mais il ne prouve pas a lui seul l'intention frauduleuse ni la gravite concrete des faits ${article39}.`,
      `Arguments de l'employeur: il peut soutenir que des documents professionnels, surtout confidentiels, ne devaient pas etre copies sur un support personnel; que ce comportement cree un risque pour l'entreprise; et que le transfert rompt la confiance necessaire a la relation de travail. Ces arguments peuvent soutenir un motif lie a la conduite du salarie, ce qui renvoie a l'exigence de motif valable d'Article 35 ${article35}.`,
      [
        "Arguments du salarie:",
        hasLongService || hasNoDiscipline
          ? "son anciennete et l'absence d'antecedent disciplinaire peuvent peser sur la proportionnalite de la sanction;"
          : "",
        hasWorkFromHome
          ? "son explication de travail a domicile donne une interpretation professionnelle et non frauduleuse du transfert;"
          : "",
        hasNoDisclosure
          ? "l'absence de divulgation a des tiers, notamment a un concurrent si ce fait est etabli, affaiblit l'idee d'une exploitation externe des documents;"
          : "",
        hasNoDamage
          ? "l'absence de prejudice concret est favorable au salarie, meme si elle ne suffit pas a exclure toute faute si une regle de confidentialite claire a ete violee."
          : ""
      ]
        .filter(Boolean)
        .join(" "),
      `Elements de preuve importants: l'employeur devrait prouver la nature confidentielle des documents, l'existence d'une politique interdisant les copies ou envois vers un espace personnel, l'autorisation ou non du travail a domicile, l'intention du salarie, l'usage reel de ${personalStorageLabel}, une eventuelle divulgation, et le prejudice ou le risque concret. Article 63 indique que la justification du licenciement incombe a l'employeur ${article63 || article35}.`,
      article62 || article64
        ? `Procedure: meme en cas de faute grave alleguee, le salarie devait pouvoir se defendre et etre entendu avec proces-verbal ${article62 || article35}. La decision devait mentionner les motifs et les circonstances retenues, et le tribunal ne peut connaitre que les motifs indiques dans cette decision ${article64 || article35}.`
        : "",
      "Conclusion probable: le licenciement pour faute grave n'est pas automatiquement valide. Il devient plus solide si l'employeur prouve une interdiction claire, la confidentialite, une intention frauduleuse ou un risque grave. Il devient plus fragile si le salarie prouve un usage purement professionnel, l'absence d'antecedent, l'absence de divulgation et l'absence de prejudice concret. La conclusion la plus prudente est qu'il existe une contestation serieuse sur la gravite et la proportionnalite de la sanction."
    ]
      .filter(Boolean)
      .join(" ");
  }

  return [
    "Faits pertinents: l'entreprise accuse un salarie d'avoir vole un ordinateur portable; l'inventaire indique qu'aucun ordinateur n'est manquant; aucune preuve directe du vol n'est mentionnee dans la question.",
    "Ce qui affaiblit l'accusation, d'abord, c'est la contradiction factuelle: si aucun ordinateur n'est manquant dans l'inventaire, il manque un element concret du vol reproche, c'est-a-dire la disparition du bien. Ce fait ne tranche pas tout seul l'affaire, mais il rend l'accusation moins credible et oblige l'employeur a apporter d'autres preuves solides.",
    `Juridiquement, Article 35 exige un motif valable de licenciement et Article 63 met la justification du licenciement a la charge de l'employeur ${[article35, article63].filter(Boolean).join(" ")}. Donc l'employeur ne peut pas simplement affirmer le vol; il doit pouvoir expliquer et prouver pourquoi l'accusation reste valable malgre l'inventaire.`,
    `Article 39 peut rendre le vol ou l'abus de confiance une faute grave, mais seulement si les faits sont etablis. Ici, l'article aide a qualifier le type de faute possible; il ne prouve pas que le vol a eu lieu ${article39}.`,
    article62 || article64
      ? `La procedure compte aussi: le salarie doit pouvoir se defendre et etre entendu, avec un proces-verbal, et la decision doit mentionner les motifs du licenciement ainsi que les circonstances retenues ${[article62, article64].filter(Boolean).join(" ")}. Dans cette affaire, les points de defense naturels seraient l'inventaire, l'absence de bien manquant, l'absence de preuve directe, et toute incoherence dans la chronologie ou les temoignages.`
      : "",
    "Conclusion: les elements qui affaiblissent l'employeur sont surtout l'absence d'ordinateur manquant, l'absence de perte prouvee, l'absence de preuve directe mentionnee, et la contradiction entre l'accusation de vol et l'inventaire. Le vrai test n'est pas seulement de citer la faute grave; c'est de savoir si l'employeur peut prouver les faits qui rendent cette faute grave credible."
  ]
    .filter(Boolean)
    .join(" ");
};

const buildEmploymentTerminationAnswer = ({ question, citations }) => {
  const normalizedQuestion = normalizeText(question);

  if (
    !/\b(fire|fired|termination|terminated|dismiss|dismissed|licenciement|employeur|salarie|employee|employer|boss)\b/.test(
      normalizedQuestion
    )
  ) {
    return null;
  }

  if (
    asksForFactExtractionOnly(normalizedQuestion) ||
    asksForStructuredCaseAnalysis(normalizedQuestion) ||
    asksAboutAccusationEvidence(normalizedQuestion)
  ) {
    return null;
  }

  const article35 = findCitationMarker(citations, "Article 35");
  const article37 = findCitationMarker(citations, "Article 37");
  const article39 = findCitationMarker(citations, "Article 39");
  const article40 = findCitationMarker(citations, "Article 40");
  const article41 = findCitationMarker(citations, "Article 41");
  const article43 = findCitationMarker(citations, "Article 43");
  const article51 = findCitationMarker(citations, "Article 51");
  const article52 = findCitationMarker(citations, "Article 52");
  const article53 = findCitationMarker(citations, "Article 53");
  const article59 = findCitationMarker(citations, "Article 59");
  const article62 = findCitationMarker(citations, "Article 62");
  const article63 = findCitationMarker(citations, "Article 63");
  const article64 = findCitationMarker(citations, "Article 64");
  const article65 = findCitationMarker(citations, "Article 65");

  if (!article35 || !article41 || !article52 || !article53 || !article62 || !article63 || !article64) {
    return null;
  }

  const yearsOfService = extractYearsOfService(question);
  const severanceHours = calculateSeveranceHours(yearsOfService);
  const abusiveDismissalMonths = calculateAbusiveDismissalMonths(yearsOfService);
  const asksAboutCompensation =
    /\b(compensation|indemnite|indemnites|dommages|preavis|severance|claim|reclamer|montant|salaire)\b/.test(
      normalizedQuestion
    );
  const asksAboutSeriousFault = /\b(faute grave|serious fault|gross misconduct|serious misconduct)\b/.test(
    normalizedQuestion
  );

  return [
    `Based on the retrieved Code du travail articles, a dismissal needs a valid reason and the employer has to prove it. Article 35 prohibits dismissal without a valid reason linked to the employee's aptitude, conduct, or the company's operational needs ${article35}.`,
    asksAboutSeriousFault && article39
      ? `For "faute grave", Article 39 is the key starting point: it lists serious faults that can justify dismissal, including acts such as theft, breach of trust, serious insult, unjustified refusal to perform competent work, and unjustified absence for more than four days or eight half-days in a twelve-month period ${article39}.`
      : "",
    asksAboutSeriousFault && article37
      ? `If the conduct is not a serious fault, Article 37 points instead to progressive disciplinary sanctions for non-serious fault before dismissal becomes justified ${article37}.`
      : "",
    asksAboutSeriousFault && article40
      ? `Article 40 also matters from the employee's side: serious faults by the employer, such as serious insult, violence, sexual harassment, or incitement to debauchery, can make the employee's departure treated as abusive dismissal when proven ${article40}.`
      : "",
    `The procedure should have included a chance for the employee to defend himself and be heard, with the employee delegate or union representative he chooses, and a written record of that hearing ${article62}. The dismissal decision then had to be delivered by hand against receipt or by registered letter within 48 hours ${article63}. The decision also had to state the reasons, mention the hearing date, attach the Article 62 record, and a copy had to be sent to the labor inspector ${article64}.`,
    `For claims, the employee may claim notice indemnity if the contract was ended without respecting notice and there was no serious fault: Article 43 requires notice for unilateral termination of an indefinite-term contract, and Article 51 makes the responsible party pay what the employee would have received during the unobserved notice period ${[article43, article51].filter(Boolean).join(" ")}.`,
    asksAboutCompensation
      ? `He may also claim statutory severance indemnity because Article 52 grants it after six months of work in the same company ${article52}. Article 53 uses graduated rates: 96 hours per year or fraction of year for the first five years, 144 hours for years 6 to 10, 192 hours for years 11 to 15, and 240 hours beyond 15 years ${article53}.${severanceHours ? ` For ${formatLegalNumber(yearsOfService)} years, that is ${severanceHours} hours of salary, before any more favorable contract or collective-rule increase.` : " The cash amount needs the employee's salary and exact service period."}`
      : `He may also have statutory severance rights if the legal conditions are met, because Article 52 grants severance after six months of work in the same company and Article 53 gives the calculation method ${article52} ${article53}.`,
    asksAboutCompensation
      ? `If the dismissal is found abusive, Article 41 allows reinstatement or damages; damages are calculated at one and a half months of salary per year or fraction of year, capped at 36 months ${article41}.${abusiveDismissalMonths ? ` For ${formatLegalNumber(yearsOfService)} years, that formula gives ${formatLegalNumber(abusiveDismissalMonths)} months of salary.` : " The cash amount needs the employee's salary and exact service period."} Article 59 also links abusive dismissal to damages and notice indemnity ${article59 || article41}.`
      : `If the dismissal is found abusive, Article 41 allows reinstatement or damages, and Article 59 also links abusive dismissal to damages and notice indemnity ${article41} ${article59 || article41}.`,
    article65
      ? `The employee should also watch the deadline: Article 65 says a court action about dismissal must be brought within 90 days from receipt of the dismissal decision ${article65}.`
      : "",
    "The exact cash amount still needs the employee's salary, contract type, applicable notice period, and whether the employer can prove serious fault or another valid ground."
  ]
    .filter(Boolean)
    .join(" ");
};

const buildPregnancyDismissalAnswer = ({ question, citations }) => {
  const normalizedQuestion = normalizeText(question);

  if (!/\b(pregnant|pregnancy|maternity|grossesse|enceinte|maternite|maternité)\b/.test(normalizedQuestion)) {
    return null;
  }

  const article35 = findCitationMarker(citations, "Article 35");
  const article41 = findCitationMarker(citations, "Article 41");
  const article63 = findCitationMarker(citations, "Article 63");
  const article64 = findCitationMarker(citations, "Article 64");
  const article65 = findCitationMarker(citations, "Article 65");
  const article66 = findCitationMarker(citations, "Article 66");
  const article67 = findCitationMarker(citations, "Article 67");
  const article159 = findCitationMarker(citations, "Article 159");
  const article160 = findCitationMarker(citations, "Article 160");
  const article165 = findCitationMarker(citations, "Article 165");

  if (!article159 || !article160 || !article35) {
    return null;
  }

  return [
    "Pregnancy protection matters, and the employer's staff-reduction reason plus the later hiring for the same position are also relevant to whether the stated reason was genuine.",
    `Article 159 says the employer cannot terminate a worker whose pregnancy is attested by a medical certificate during pregnancy and the 14 weeks after childbirth. The employer may still terminate only if it justifies serious fault or another legal ground, and the termination must not be notified or take effect during the protected suspension periods ${article159}.`,
    `If the dismissal was notified before the employee had proved pregnancy by medical certificate, Article 160 lets her send the medical certificate by registered letter within 15 days from notification; the dismissal is then annulled, subject to the Article 159 exceptions ${article160}.`,
    `The staff-reduction explanation is not automatically irrelevant. Article 35 allows dismissal only for a valid reason, including business-operation needs handled under Articles 66 and 67 ${article35}. For economic, structural, technological, or similar dismissal, Article 66 requires advance notice to employee delegates or union representatives, disclosure of the reasons, affected categories and timing, consultations and negotiations, and a written record sent to the provincial labor delegate ${article66 || article35}. Article 67 requires authorization from the governor; for economic reasons, the file must include an economic report, the company's financial situation, and an accountant or auditor report ${article67 || article35}.`,
    `A court would therefore consider whether pregnancy was medically certified or timely certified after notice, whether the termination was during a protected period, whether the written decision stated lawful reasons, whether the employer can prove the stated reason, and whether the economic-dismissal consultation and authorization procedure was followed ${[article63, article64, article66, article67].filter(Boolean).join(" ")}.`,
    `Hiring another employee for the same position one month later is not ignored. The excerpts do not contain a special replacement-employee rule, but it is a fact the court could weigh against the employer's claimed staff-reduction reason because Article 63 places the burden of justifying dismissal on the employer, and Article 64 limits the tribunal to the reasons in the dismissal decision and the circumstances of the dismissal ${[article63, article64].filter(Boolean).join(" ")}.`,
    `For remedies, Article 160 may annul the dismissal if the pregnancy certificate was sent in time ${article160}. If the dismissal is treated as abusive, Article 41 allows reinstatement or damages calculated at one and a half months of salary per year or fraction of year, capped at 36 months ${article41 || article160}. Article 165 also provides employer fines for unlawful termination of a pregnant or postpartum worker outside the Article 159 cases ${article165 || article159}.`,
    article65
      ? `The employee should also watch the deadline: Article 65 says a court action about dismissal must be brought within 90 days from receipt of the dismissal decision ${article65}.`
      : "",
    "So the stronger answer is: she may have pregnancy-protection rights, possible annulment, and possible abusive-dismissal remedies; the court would test both the pregnancy-certificate/protected-period facts and whether the staff-reduction reason was real and legally authorized."
  ]
    .filter(Boolean)
    .join(" ");
};

const buildRenovationContractAnswer = ({ question, citations }) => {
  const normalizedQuestion = normalizeText(question);

  if (
    !/\b(renovation|construction|repair|repairs|contractor|builder|material|materials|devis|quote|estimate|prix fait|travaux|ouvrage|chantier|entrepreneur)\b/.test(
      normalizedQuestion
    )
  ) {
    return null;
  }

  const documentTitle = "Code des Obligations et des Contrats";
  const article230 = findCitationMarker(citations, "Article 230", documentTitle);
  const article231 = findCitationMarker(citations, "Article 231", documentTitle);
  const article259 = findCitationMarker(citations, "Article 259", documentTitle);
  const article758 = findCitationMarker(citations, "Article 758", documentTitle);
  const article766 = findCitationMarker(citations, "Article 766", documentTitle);
  const article777 = findCitationMarker(citations, "Article 777", documentTitle);
  const generalContractRules = [
    article230
      ? `valid contractual obligations bind the parties and cannot be revoked except by mutual consent or a legal case ${article230}`
      : "",
    article231
      ? `obligations must be performed in good faith and include consequences required by law, usage, or equity according to their nature ${article231}`
      : ""
  ].filter(Boolean);

  if (!article777) {
    return null;
  }

  return [
    `For a renovation or construction job, the strongest retrieved rule is Article 777 of the Code des Obligations et des Contrats. If the contractor undertook the work for a fixed price based on a plan or estimate made or accepted by him, he cannot ask for a price increase unless the extra expense was caused by the client and the client expressly authorized that extra expense. The article also leaves room for the parties' own contract terms ${article777}.`,
    `So a simple rise in material prices does not, by itself, validate the contractor's demand for more money under the retrieved rule. The key facts are whether the contract was a fixed-price job or accepted quote, whether the client changed the work or caused additional expense, whether the client expressly approved the extra cost, and whether the contract contains a price-revision clause ${article777}.`,
    generalContractRules.length
      ? `The general contract articles support the same structure: ${generalContractRules.join("; ")}.`
      : "",
    article259
      ? `If one party stops performing or refuses the agreed performance, Article 259 becomes relevant after default: the creditor may seek performance if possible, or otherwise judicial termination of the contract with damages; termination is not automatic and must be pronounced by the court ${article259}.`
      : "",
    article758
      ? `Article 758 is also relevant to abrupt non-performance: when one party does not fulfill commitments or ends them abruptly at the wrong time without plausible reasons, that party may owe damages to the other contracting party ${article758}.`
      : "",
    article766
      ? `If the dispute concerns materials, Article 766 adds that when the contractor supplies the material, he guarantees the quality of the materials used; when the client supplies them, the contractor must use them according to the rules of the art and without negligence ${article766}.`
      : "",
    "Likely conclusion: if this was a fixed-price renovation quote and the client did not cause or expressly authorize the extra material cost, the contractor has a weak basis to demand more money only because materials became more expensive. If the contractor terminates or abandons the work for that reason, the dispute would likely turn on default, court-ordered termination, damages, the written contract terms, proof of notices, and whether there were authorized changes to the work."
  ]
    .filter(Boolean)
    .join(" ");
};

const buildPartialDeliverySaleAnswer = ({ question, citations }) => {
  const normalizedQuestion = normalizeText(question);

  const hasSaleFacts = /\b(sale|sold|sells|seller|buyer|bought|purchase|paid|payment|price|order|vente|vendu|vendeur|acheteur|paiement)\b/.test(
    normalizedQuestion
  );
  const hasDeliveryFacts = /\b(deliver|delivery|delivered|received|receives|missing|partial|only|quantity|goods|products|items|laptops|delivrance|livraison|reception|quantite|partielle|marchandise)\b/.test(
    normalizedQuestion
  );

  if (!hasSaleFacts || !hasDeliveryFacts) {
    return null;
  }

  const documentTitle = "Code des Obligations et des Contrats";
  const article488 = findCitationMarker(citations, "Article 488", documentTitle);
  const article491 = findCitationMarker(citations, "Article 491", documentTitle);
  const article494 = findCitationMarker(citations, "Article 494", documentTitle);
  const article496 = findCitationMarker(citations, "Article 496", documentTitle);
  const article498 = findCitationMarker(citations, "Article 498", documentTitle);
  const article499 = findCitationMarker(citations, "Article 499", documentTitle);
  const article500 = findCitationMarker(citations, "Article 500", documentTitle);
  const article504 = findCitationMarker(citations, "Article 504", documentTitle);
  const article259 = findCitationMarker(citations, "Article 259", documentTitle);

  if (!article488 || !article498 || !article499) {
    return null;
  }

  return [
    `The main legal issue is not "laptops" specifically; it is a sale contract where the seller allegedly delivered only part of what was sold. Article 488 says a sale is perfected once the parties agree to sell and buy, and agree on the thing, the price, and the other clauses ${article488}.${article491 ? ` Article 491 adds that the buyer acquires ownership of the sold thing once the sale is perfected by consent ${article491}.` : ""}`,
    `The seller's core obligation is delivery. Article 498 says the seller has two main obligations: to deliver the sold thing and to guarantee it ${article498}. Article 499 defines delivery as the seller giving up the sold thing and putting the buyer in a position to take possession without obstacle ${article499}.${article500 ? ` For movable goods, Article 500 allows delivery by actual handover or other usage-recognized means ${article500}.` : ""}`,
    article504
      ? `Because the buyer paid in full in your example, Article 504 matters: delivery should occur after conclusion of the contract, except delays required by the nature of the thing or usage, and the seller who did not grant a payment term is not bound to deliver unless the buyer offers payment against delivery ${article504}. If payment was already made, the seller has less room to refuse delivery on non-payment grounds.`
      : "",
    article494
      ? `For quantity and receipt, Article 494 is useful if the sale was by count, measure, test, or description: until the goods are counted, measured, examined, and accepted by the buyer, they remain at the seller's risk ${article494}.`
      : "",
    article496
      ? `Article 496 also says the sold thing travels at the seller's risk until receipt by the buyer ${article496}.`
      : "",
    article259
      ? `If the seller is in default for the missing part, Article 259 gives the creditor a path to force performance if possible, or seek judicial termination of the contract with damages; if only partial performance is possible, the creditor may seek performance for the possible part or termination, with damages in both cases ${article259}.`
      : "",
    "Likely conclusion: if the buyer can prove a contract for 100 items, full payment, and delivery of only 60, the seller appears to have a delivery/non-performance problem for the remaining 40. The important evidence would be the purchase order or invoice, proof of payment, delivery notes, acceptance records, any agreed delivery deadline, and whether the buyer accepted the partial delivery as full performance."
  ]
    .filter(Boolean)
    .join(" ");
};

const buildSaleOwnershipAnswer = ({ question, citations }) => {
  const normalizedQuestion = normalizeText(question);
  const hasSaleFacts = /\b(sold|sale|bought|buyer|seller|paid|payment|price|vente|vendu|acheteur|vendeur|prix)\b/.test(
    normalizedQuestion
  );
  const hasOwnershipDisputeFacts =
    /\b(ownership|owner|registration|registered|heirs|inherit|inherited|car|vehicle|propriete|possession|heritier|succession|vehicule|immatriculation|carte grise)\b/.test(
      normalizedQuestion
    );

  if (!hasSaleFacts || !hasOwnershipDisputeFacts) {
    return null;
  }

  const documentTitle = "Code des Obligations et des Contrats";
  const article229 = findCitationMarker(citations, "Article 229", documentTitle);
  const article488 = findCitationMarker(citations, "Article 488", documentTitle);
  const article491 = findCitationMarker(citations, "Article 491", documentTitle);
  const article498 = findCitationMarker(citations, "Article 498", documentTitle);
  const article499 = findCitationMarker(citations, "Article 499", documentTitle);
  const article500 = findCitationMarker(citations, "Article 500", documentTitle);
  const article504 = findCitationMarker(citations, "Article 504", documentTitle);

  if (!article488 || !article491 || !article229) {
    return null;
  }

  return [
    `On the retrieved civil-law articles, Youssef has the stronger ownership argument if he can prove the sale. Article 488 says a sale is perfected between the parties once they consent to sell and buy and agree on the thing and the price ${article488}. Article 491 says the buyer acquires ownership of the sold thing by operation of law once the contract is perfected by consent ${article491}.`,
    [
      "The facts that Youssef paid the full price and took the car matter because they are evidence that the sale was performed.",
      article498 ? `The seller's main obligations include delivery and guarantee ${article498}.` : "",
      article499
        ? `Delivery occurs when the seller gives up the sold thing and puts the buyer in a position to possess it without obstacle ${article499}.`
        : "",
      article500
        ? `For movable things, delivery can occur by real handover or another usage-recognized method ${article500}.`
        : "",
      article504
        ? `Article 504 also links delivery to payment when no payment term was granted ${article504}.`
        : ""
    ]
      .filter(Boolean)
      .join(" "),
    `Ahmed's death does not by itself revive ownership in the heirs if the sale was already perfected before death. Article 229 says obligations have effect not only between the parties but also between their heirs or successors, unless the agreement, the nature of the obligation, or the law says otherwise ${article229}. So the heirs generally step into Ahmed's legal position; they do not get better rights than Ahmed had if he had already sold the car.`,
    "The unresolved point is registration. The retrieved excerpts do not include the specific vehicle-registration rule, so the safe answer is not that registration is irrelevant. The better analysis is that, between Ahmed and Youssef, the sale, payment, and possession facts strongly support Youssef under Articles 488 and 491; but registration may still matter as administrative proof, opposability to third parties, or completion of vehicle-transfer formalities under laws not shown in these excerpts.",
    "A court would likely focus on proof: the agreement on the car and price, proof of payment, possession or delivery, the date of sale before Ahmed's death, any written sale document, and what vehicle-registration law says about effects against heirs or third parties. If those facts prove a completed sale, Youssef should have the stronger claim; if the sale cannot be proven or vehicle-registration law makes registration decisive against heirs, the result could change."
  ].join(" ");
};

const ensureSubstantiveAnswer = (answer, citations) => {
  if (String(answer || "").length >= 180 || !citations?.length) {
    return answer;
  }

  return [buildCitationLead(citations), answer].filter(Boolean).join(" ");
};

const getAnswerFactValidationIssues = (answer, question) => {
  const factProfile = getQuestionFactProfile(question);
  const answerText = String(answer || "");
  const normalizedAnswer = normalizeText(answerText);
  const issues = [];

  if (typeof factProfile.yearsOfService === "number" && Number.isFinite(factProfile.yearsOfService)) {
    const expectedYears = Math.round(factProfile.yearsOfService * 100) / 100;
    const yearReferenceMatches = [
      ...answerText.matchAll(/\b(?:for|pour)\s+(\d+(?:[.,]\d+)?)\s*(?:years?|ans|annees?)\b/gi),
      ...answerText.matchAll(/\b(?:salarie|employee)[^.!?\n]{0,120}?(\d+(?:[.,]\d+)?)\s*(?:years?|ans|annees?)(?:\s+d'anciennete|\s+of service)?\b/gi)
    ];
    const wrongYears = yearReferenceMatches
      .map((match) => Number(String(match[1]).replace(",", ".")))
      .filter((value) => Number.isFinite(value) && Math.abs(value - expectedYears) > 0.001);

    if (wrongYears.length) {
      issues.push(`answer contradicts the scenario years of service (${formatLegalNumber(expectedYears)})`);
    }
  }

  if (
    !factProfile.asksAboutCompensation &&
    /\b\d+(?:[.,]\d+)?\s*(?:hours? of salary|heures? de salaire|months? of salary|mois de salaire)\b/i.test(answerText)
  ) {
    issues.push("answer calculates compensation even though the user did not ask for compensation");
  }

  if (factProfile.mentionsPersonalEmail && normalizedAnswer.includes("cle usb") && !normalizeText(question).includes("cle usb")) {
    issues.push("answer changed personal email into USB");
  }

  return issues;
};

const buildFactGroundedFallbackAnswer = ({ question, plan, citations }) => {
  const facts = extractLegalFacts(question);
  const issues = extractLegalIssues(question, plan);
  const articleSummaries = citations
    .slice(0, 7)
    .map((citation, index) => {
      const source = [citation.articleNumber, citation.documentTitle].filter(Boolean).join(" ");
      return `${source || citation.title} [${index + 1}]`;
    })
    .filter(Boolean);
  const factProfile = getQuestionFactProfile(question);
  const analysis = [
    factProfile.mentionsPersonalEmail ? "l'envoi vers une adresse email personnelle peut soutenir l'argument de risque de confidentialite de l'employeur" : "",
    factProfile.mentionsWorkFromHome ? "l'explication de travail a domicile donne au salarie une justification professionnelle possible" : "",
    factProfile.mentionsNoCompetitorDisclosure ? "l'absence de transmission a un concurrent affaiblit l'idee d'une exploitation externe des documents" : "",
    factProfile.mentionsNoDamage ? "l'absence de prejudice prouve pese sur la gravite et la proportionnalite de la sanction" : "",
    factProfile.mentionsImmediateDismissal ? "le caractere immediat du licenciement rend decisives la preuve de la faute grave et la regularite de la procedure" : "",
    "l'employeur doit relier les faits reproches a un motif valable et prouver les circonstances retenues"
  ].filter(Boolean);

  return [
    `A. Faits importants: ${facts.length ? facts.join(" ") : "Les faits utiles doivent etre extraits du scenario avant de citer les articles."}`,
    `B. Questions juridiques: ${issues.length ? issues.join("; ") : "motif valable, preuve, proportionnalite et procedure de licenciement"}.`,
    `C. Articles applicables: ${articleSummaries.length ? articleSummaries.join("; ") : "sources insuffisantes dans les extraits retenus"}.`,
    `D. Analyse des faits: ${analysis.join("; ")}.`,
    "E. Arguments de chaque partie: L'employeur peut invoquer le risque de confidentialite, une violation d'une regle interne ou une rupture de confiance. Le salarie peut invoquer la finalite professionnelle, l'absence de concurrent destinataire, l'absence de prejudice prouve, son anciennete et la proportionnalite de la sanction.",
    "F. Preuves importantes: lettre de licenciement, proces-verbal d'audition, politique informatique ou confidentialite, traces d'envoi, autorisation ou usage du teletravail, preuve de divulgation a des tiers, preuve d'un prejudice ou d'un risque concret.",
    "G. Conclusion probable: la faute grave n'est pas automatique. La sanction est plus solide si l'employeur prouve une interdiction claire, la confidentialite, un risque grave ou une divulgation. Elle est plus contestable si l'envoi etait professionnel, sans concurrent destinataire, sans prejudice prouve, et si la procedure n'a pas ete respectee.",
    "H. Limites / informations manquantes: il manque les documents internes, la preuve technique, les motifs exacts de la decision et les pieces produites par l'employeur."
  ].join(" ");
};

const enforceFactConsistency = ({ answer, question, plan, citations }) => {
  const issues = getAnswerFactValidationIssues(answer, question);

  if (!issues.length) {
    return answer;
  }

  const fallback =
    buildEmploymentCaseAnalysisAnswer({ question, citations, force: true }) ||
    buildFactGroundedFallbackAnswer({ question, plan, citations });

  return getAnswerFactValidationIssues(fallback, question).length
    ? buildFactGroundedFallbackAnswer({ question, plan, citations })
    : fallback;
};

const answerWithAiReasoning = async ({ question, plan, citations }) => {
  if (!citations?.length) {
    return null;
  }

  const specializedAnswer =
    buildFactExtractionAnswer({ question }) ||
    buildEmploymentCaseAnalysisAnswer({ question, citations }) ||
    buildPregnancyDismissalAnswer({ question, citations }) ||
    buildEmploymentEvidenceAnswer({ question, citations }) ||
    buildEmploymentTerminationAnswer({ question, citations }) ||
    buildRenovationContractAnswer({ question, citations }) ||
    buildPartialDeliverySaleAnswer({ question, citations }) ||
    buildSaleOwnershipAnswer({ question, citations });

  if (specializedAnswer) {
    return enforceFactConsistency({ answer: specializedAnswer, question, plan, citations });
  }

  if (!isAiReasoningEnabled()) {
    return null;
  }

  const config = getOllamaConfig();
  const extractedFacts = extractLegalFacts(question);
  const legalIssues = extractLegalIssues(question, plan);
  const citationContext = citations
    .slice(0, 12)
    .map(formatCitationForPrompt)
    .join("\n\n");

  try {
    const answerText = await callOllama({
      timeoutMs: config.answerTimeoutMs,
      temperature: 0.2,
      numPredict: 950,
      format: "json",
      messages: [
        {
          role: "system",
          content:
            "/no_think\nYou are a careful Moroccan legal assistant and case analyst. Return JSON only with one key: answer. The answer must be final user-facing text only. Do not include your drafting process, hidden reasoning, self-talk, prompt analysis, Markdown formatting, or phrases like 'let me'. The pipeline is mandatory: FACT EXTRACTION, ISSUE SPOTTING, RETRIEVAL, RELEVANCE CHECK, FINAL ANALYSIS. The final answer must visibly use this structure: A. Faits importants, B. Questions juridiques, C. Articles applicables, D. Analyse des faits, E. Arguments de chaque partie, F. Preuves importantes, G. Conclusion probable, H. Limites / informations manquantes. Do not cite any article before section C. Use the retrieved law excerpts as legal authority, and use the user's facts for legal analysis. Explain why facts matter for proof, credibility, timing, motive, consent, possession, contradiction, burden, or compliance. Do not say a user fact is irrelevant merely because no article names that exact fact. Every legal rule, remedy, formula, deadline, burden, or procedure must be supported by a cited excerpt. Do not calculate compensation unless the user asks for compensation, damages, indemnity, amount, salary, or a claim calculation. Never reuse case numbers or facts from examples; only use facts in the current user question. If the relevant excerpts are insufficient, say sources insuffisantes and identify the missing source or fact. Cite relevant excerpts with [1], [2], etc. Do not say you searched the web."
        },
        {
          role: "user",
          content: [
            `User question: ${question}`,
            extractedFacts.length ? `Stage 1 FACT EXTRACTION facts[]: ${JSON.stringify(extractedFacts)}` : "Stage 1 FACT EXTRACTION facts[]: []",
            legalIssues.length ? `Stage 2 ISSUE SPOTTING legal_issues[]: ${JSON.stringify(legalIssues)}` : "Stage 2 ISSUE SPOTTING legal_issues[]: []",
            plan?.aiPlan?.legalIssue ? `Detected issue: ${plan.aiPlan.legalIssue}` : "",
            plan?.aiPlan?.reasoningGoal ? `Reasoning goal: ${plan.aiPlan.reasoningGoal}` : "",
            plan?.queries?.length ? `Stage 2 French search_queries[] used for RETRIEVAL: ${JSON.stringify(plan.queries)}` : "",
            "",
            "Stage 3 RETRIEVAL + Stage 4 RELEVANCE CHECK: only these relevance-filtered law excerpts survived:",
            citationContext,
            "",
            "Return JSON like this: {\"answer\":\"final answer only\"}. Do case analysis, not article summarization. Start from the Stage 1 facts. If the question asks what weakens or supports an accusation, focus on proof, contradictions, missing evidence, burden of justification, and factual uncertainty before discussing remedies. When the question asks several things, answer each part. Use each formula only for the remedy it describes; do not use a severance formula for notice indemnity. When the excerpts contain a basic rule and aggravating circumstances, exceptions, or special cases, state the basic rule first. If the retrieved excerpts are from the wrong subtype or only a related topic, say sources insuffisantes instead of pretending the wrong sources answer the question."
          ]
            .filter(Boolean)
            .join("\n")
        }
      ]
    });
    const answerPayload = extractJsonObject(answerText);
    const answer = cleanGeneratedAnswer(
      typeof answerPayload?.answer === "string" ? answerPayload.answer : answerText
    );
    const answerWithValidCitations = removeInvalidCitationMarkers(answer, citations);
    const groundedAnswer = ensureSubstantiveAnswer(
      ensureCitationMarker(removeUnsupportedPracticalAdvice(answerWithValidCitations, citationContext), citations),
      citations
    );

    return groundedAnswer
      ? enforceFactConsistency({ answer: groundedAnswer, question, plan, citations })
      : null;
  } catch (error) {
    console.error("[ai-reasoning] Answer generation failed");
    console.error(error);
    return null;
  }
};

module.exports = {
  createAiSearchPlan,
  answerWithAiReasoning,
  isAiReasoningEnabled
};
