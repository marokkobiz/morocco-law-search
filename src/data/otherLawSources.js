const otherLawSources = [
  {
    documentTitle: "Code de commerce",
    lawReference: "Loi n 15-95",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/01/Code%20de%20commerce_compressed-1709282723074.pdf",
    tags: ["commercial", "business", "commerce", "traders"]
  },
  {
    documentTitle: "Societes anonymes",
    lawReference: "Loi n 17-95",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/12/Soci%C3%A9t%C3%A9s%20Anonymes-1734015032558.pdf",
    tags: ["commercial", "companies", "corporate", "shares"]
  },
  {
    documentTitle: "Protection du consommateur",
    lawReference: "Loi n 31-08",
    category: "consumer",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/01/protection%20de%20consommateur-1709283839988.pdf",
    tags: ["consumer", "contracts", "commerce", "protection"]
  },
  {
    documentTitle: "Etablissements de credit et organismes assimiles",
    lawReference: "Loi n 103-12",
    category: "banking",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/15/loi%20relative%20aux%20etablissements%20de%20credit%20et%20organismes%20assimiles-1710514746601.pdf",
    tags: ["banking", "finance", "credit", "commercial"]
  },
  {
    documentTitle: "Code du travail",
    lawReference: "Loi n 65-99",
    category: "labor",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/30/code%20du%20travail-1714463246806.pdf",
    tags: ["labor", "employment", "workers", "contracts"]
  },
  {
    documentTitle: "Code penal",
    lawReference: "Dahir n 1-59-413",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/03/Dahir%20n%C2%B0%201-59-413%20portant%20approbation%20du%20texte%20du%20code%20penal-1743685280109.pdf",
    tags: ["criminal", "penal", "offences", "sanctions"]
  },
  {
    documentTitle: "Code des Obligations et des Contrats",
    lawReference: "Dahir du 12 aout 1913",
    category: "civil",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/02/28/Code%20des%20Obligations%20et%20des%20Contrats_compressed-1709126934943.pdf",
    tags: ["civil", "contracts", "obligations", "liability"]
  },
  {
    documentTitle: "Code de procedure civile",
    lawReference: "Dahir portant loi n 1-74-447",
    category: "civil-procedure",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/02/28/Code%20de%20proc%C3%A9dure%20civile-1709129409071.pdf",
    tags: ["civil", "procedure", "courts", "litigation"]
  },
  {
    documentTitle: "Code de la famille",
    lawReference: "Loi n 70-03",
    category: "family",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/12/DAHIR%20N%C2%B0%201-04-22%20PORTANT%20PROMULGATION%20DE%20LA%20LOI.PDF-1734012188728.pdf",
    tags: ["family", "marriage", "children", "inheritance"]
  },
  {
    documentTitle: "Immatriculation fonciere",
    lawReference: "Dahir du 12 aout 1913",
    category: "real-estate",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/19/Immatriculation%20fonci%C3%A8re-1710848548570.pdf",
    tags: ["real-estate", "land-title", "registration", "property-rights"]
  },
  {
    documentTitle: "Liberte des prix et de la concurrence",
    lawReference: "Loi n 104-12",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/01/Libert%C3%A9%20des%20prix%20et%20de%20la%20concurrence-1709283060147.pdf",
    tags: ["commercial", "competition", "pricing", "market-regulation"]
  },
  {
    documentTitle: "Loi relative au statut de l'auto-entrepreneur",
    lawReference: "Loi n 114-13",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/05/07/Loi%20Relative%20au%20statut%20de%20l%E2%80%99auto-entrepreneur-1715071318209.pdf",
    tags: ["commercial", "entrepreneurship", "small-business", "self-employed"]
  },
  {
    documentTitle: "Charte de l'investissement",
    lawReference: "Loi-cadre n 03-22",
    category: "investment",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/10/14/charte%20de%20l%27investissement-1728902435500.pdf",
    tags: ["investment", "business", "incentives", "economic-development"]
  },
  {
    documentTitle: "Suretes mobilieres",
    lawReference: "Loi n 21-18",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/02/29/Sur%C3%AAtes%20mobili%C3%A8res-1709211797900.pdf",
    tags: ["commercial", "secured-transactions", "financing", "collateral"]
  },
  {
    documentTitle: "Charte de la petite et moyenne entreprise",
    lawReference: "Loi n 53-00",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/20/LOI%20FORMANT%20LA%20CHARTE%20DE%20LA%20PETITE%20ET%20MOYENNE%20ENTREPRISE-1710934635399.pdf",
    tags: ["commercial", "pme", "small-business", "enterprise-support"]
  },
  {
    documentTitle: "Societe en nom collectif et SARL",
    lawReference: "Loi n 5-96",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/26/LA%20SOCIETE%20EN%20NOM%20COLLECTIF-1711463631100.pdf",
    tags: ["commercial", "companies", "sarl", "partnerships"]
  },
  {
    documentTitle: "Mesures de defense commerciale",
    lawReference: "Loi n 15-09",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/20/MESURES%20DE%20DEFENSE%20COMMERCIALE-1710936730596.pdf",
    tags: ["commercial", "trade", "imports", "anti-dumping"]
  },
  {
    documentTitle: "Marche a terme d'instruments financiers",
    lawReference: "Loi n 42-12",
    category: "banking",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/15/LOI%20RELATIVE%20AU%20MARCHE%20A%20TERME%20D%27INSTRUMENTS%20FINANCIERS-1710512795770.pdf",
    tags: ["banking", "finance", "capital-markets", "financial-instruments"]
  }
];

module.exports = {
  otherLawSources
};
