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
  },
  {
    documentTitle: "Code de recouvrement des creances publiques",
    lawReference: "Loi n 15-97",
    category: "public-finance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/13/Code%20de%20recouvrement%20%282%29-1734081544501.pdf",
    tags: ["public-finance", "tax", "public-debt", "state-claims"]
  },
  {
    documentTitle: "Carte nationale d'identite electronique",
    lawReference: "Loi n 04-20",
    category: "electronic-transactions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/22/Carte%20Nationale%20d%27Identit%C3%A9%20El%C3%A9ctronique-1711116654213.pdf",
    tags: ["electronic-transactions", "identity", "digital-id", "administrative"]
  },
  {
    documentTitle: "Echange electronique des donnees juridiques",
    lawReference: "",
    category: "electronic-transactions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/26/%C3%89change%20%C3%A9lectronique%20des%20donn%C3%A9es%20juridiques-1711452342909.pdf",
    tags: ["electronic-transactions", "digital", "documents", "e-government"]
  },
  {
    documentTitle: "Protection des donnees a caractere personnel",
    lawReference: "",
    category: "electronic-transactions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/30/Protection%20des%20personnes%20physiques-1714464099884.pdf",
    tags: ["electronic-transactions", "privacy", "personal-data", "compliance"]
  },
  {
    documentTitle: "Services de confiance pour les transactions electroniques",
    lawReference: "",
    category: "electronic-transactions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/20/Service%20de%20Confiance%20pour%20les%20transactions%20%C3%A9l%C3%A9ctroniques-1710937527202.pdf",
    tags: ["electronic-transactions", "digital-signature", "trust-services", "e-commerce"]
  },
  {
    documentTitle: "La cybersecurite",
    lawReference: "",
    category: "electronic-transactions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/20/La%20cybers%C3%A9curit%C3%A9-1710937403606.pdf",
    tags: ["electronic-transactions", "cybersecurity", "information-systems", "digital"]
  },
  {
    documentTitle: "Conditions de travail des travailleurs domestiques",
    lawReference: "",
    category: "labor",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/01/Conditions%20de%20travail%20et%20d%27mploi%20des%20travailleuses%20et%20travail-1709304131036.pdf",
    tags: ["labor", "domestic-workers", "employment", "social"]
  },
  {
    documentTitle: "Formation continue des salaries du secteur prive",
    lawReference: "Loi n 60-17",
    category: "labor",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/11/10/Dahir%20n%C2%B0%201-18-94%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2060-17%20relative%20%C3%A0%20l%27organisation%20de%20la%20formation%20continue%20au%20profit%20des%20salari%C3%A9s%20du%20secteur%20pr-1762787289124.pdf",
    tags: ["labor", "training", "private-sector", "employment"]
  },
  {
    documentTitle: "Arbitrage et mediation conventionnelle",
    lawReference: "Loi n 95-17",
    category: "civil",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/02/20/Dahir%20n%C2%BA%201-22-34%20%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2095-17%20relative%20%C3%A0%20l%27arbitrage%20et%20la%20m%C3%A9diation%20conventionnelle_-1771582954512.pdf",
    tags: ["civil", "arbitration", "mediation", "dispute-resolution"]
  },
  {
    documentTitle: "Securite des produits et des services",
    lawReference: "",
    category: "consumer",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/01/La%20securite%20des%20produits%20et%20des%20services-1709302163929.pdf",
    tags: ["consumer", "product-safety", "services", "compliance"]
  },
  {
    documentTitle: "Dahir sur la procedure civile 1913",
    lawReference: "",
    category: "civil-procedure",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/09/27/Dahir%20sur%20la%20proc%C3%A9dure%20civile%201913-1727428229369.pdf",
    tags: ["civil-procedure", "courts", "procedure", "historical"]
  },
  {
    documentTitle: "Dahir sur l'assistance judiciaire 1913",
    lawReference: "",
    category: "civil-procedure",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/09/27/Dahir%20sur%20l%27assissatnce%20judiciaire%201913-1727428286973.pdf",
    tags: ["civil-procedure", "legal-aid", "justice", "historical"]
  },
  {
    documentTitle: "Cooperatives",
    lawReference: "Loi n 112-12",
    category: "commercial",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/09/Cooperatives-1712660938413.pdf",
    tags: ["commercial", "cooperatives", "social-economy", "enterprise"]
  },
  {
    documentTitle: "Statut de la mutualite",
    lawReference: "Loi n 39-22",
    category: "rights-liberties",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/06/18/Dahir%20n%C2%BA%201-23-59%20du%2023%20moharrem%201445%20%2810%20ao%C3%BBt%202023%29-1750252739583.pdf",
    tags: ["rights-liberties", "mutuality", "social-protection", "associations"]
  },
  {
    documentTitle: "Appels a la generosite publique",
    lawReference: "",
    category: "rights-liberties",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/appels%20%C3%A0%20la%20g%C3%A9n%C3%A9rosit%C3%A9%20publique-1711531831919.pdf",
    tags: ["rights-liberties", "public-charity", "associations", "fundraising"]
  },
  {
    documentTitle: "Violences faites aux femmes",
    lawReference: "Loi n 103-13",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/02/29/lutte%20contre%20les%20violences%20faites%20aux%20femmes-1709213422103.pdf",
    tags: ["criminal", "women", "violence", "protection"]
  },
  {
    documentTitle: "Lutte contre la traite des etres humains",
    lawReference: "Loi n 27-14",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/30/loi%20relative%20a%20la%20lutte%20contre%20la%20traite%20des%20etres%20humains-1714486242236.pdf",
    tags: ["criminal", "human-trafficking", "protection", "offences"]
  },
  {
    documentTitle: "Statut de Bank Al-Maghrib",
    lawReference: "Loi n 40-17",
    category: "banking",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/22/Dahir%201-19-82%20portant%20promulgation%20de%20la%20loi%20n%C2%B040-17%20portant%20statut%20de%20Bank%20Al-Maghrib-1745315368289.pdf",
    tags: ["banking", "central-bank", "finance", "regulation"]
  },
  {
    documentTitle: "Places financieres offshore",
    lawReference: "Loi n 58-90",
    category: "banking",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/22/Dahir%20n%C2%B0%201-91-131%20du%2026%20f%C3%A9vrier%201992%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2058-90%20relative-1745313166134.pdf",
    tags: ["banking", "offshore", "finance", "investment"]
  },
  {
    documentTitle: "Entree et sejour des etrangers au Maroc",
    lawReference: "Loi n 02-03",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/30/Entr%C3%A9e%20et%20au%20s%C3%A9jour%20des%20%C3%A9trangers%20au%20Maroc-1714463632505.pdf",
    tags: ["criminal", "immigration", "foreigners", "residency"]
  },
  {
    documentTitle: "Lutte contre le blanchiment de capitaux",
    lawReference: "Loi n 43-05",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/18/DAHIRN_1%20%281%29%20%281%29-1734518483721.pdf",
    tags: ["criminal", "money-laundering", "finance", "compliance"]
  },
  {
    documentTitle: "Organisation des missions de la medecine legale",
    lawReference: "Loi n 77-17",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/15/L%E2%80%99EXERCICE%20DES%20MISSIONS%20DE%20LA%20MEDECINE%20LEGALE-1710503463673.pdf",
    tags: ["criminal", "forensics", "medical", "justice"]
  },
  {
    documentTitle: "Dahir sur l'assessorat en matiere criminelle",
    lawReference: "1913",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/10/08/dahir%20sur%20les%20assesseurs%20en%20mati%C3%A9re%20criminelle%201913-1728380871301.pdf",
    tags: ["criminal", "procedure", "courts", "historical"]
  },
  {
    documentTitle: "Graces",
    lawReference: "Dahir n 1-57-387",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/02/29/Gr%C3%A2ces-1709213936586.pdf",
    tags: ["criminal", "grace", "sentencing", "royal-prerogative"]
  },
  {
    documentTitle: "Tutelle administrative sur les collectivites ethniques",
    lawReference: "Loi n 62-17",
    category: "civil",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/07/22/Dahir%20n%C2%B0%201%E2%80%9119%E2%80%91115%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2062%E2%80%9117%20relative%20%C3%A0%20la%20...-1721659890185.pdf",
    tags: ["civil", "collective-lands", "communities", "land-governance"]
  },
  {
    documentTitle: "Delimitation administrative des terres des collectivites ethniques",
    lawReference: "Loi n 63-17",
    category: "civil",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/07/22/Dahir%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2063%E2%80%9117%20relative%20%C3%A0%20la%20d%C3%A9limitation...%20%281%29-1721659776755.pdf",
    tags: ["civil", "collective-lands", "boundaries", "land-governance"]
  },
  {
    documentTitle: "Condition civile des francais et des etrangers dans le protectorat",
    lawReference: "1913",
    category: "civil",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/11/08/Dahir%20sur%20la%20condition%20civile%20des%20fran%C3%A7ais%20et%20des%20%C3%A9trangers%20dans%20le%20Protectorat%20fran%C3%A7ais%20du%20Maroc-1731055138504.pdf",
    tags: ["civil", "status", "foreigners", "historical"]
  },
  {
    documentTitle: "Lutte contre le dopage dans le sport",
    lawReference: "Loi n 97-12",
    category: "criminal",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/05/Dahir%20n%C2%B0%201-17-26%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2097-12%20relative%20%C3%A0%20la%20lutte%20contre%20le%20dopage%20dans%20le%20sport-1764922609153.pdf",
    tags: ["criminal", "sport", "doping", "health"]
  },
  {
    documentTitle: "Systeme national de sante",
    lawReference: "Loi-cadre",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/01/16/Loi-cadre%20relative%20au%20syst%C3%A8me%20national%20de%20sante-1737024163318.pdf",
    tags: ["health", "public-health", "healthcare", "medical-system"]
  },
  {
    documentTitle: "Protection des personnes participant aux recherches biomedicales",
    lawReference: "Loi n 28-13",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/05/07/protection%20des%20personnes%20participant%20aux%20re-1715071516931.pdf",
    tags: ["health", "biomedical", "research", "medical-ethics"]
  },
  {
    documentTitle: "Securite sanitaire du sel alimentaire",
    lawReference: "Decret n 2-22-831",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/07/19/D%C3%A9cret%20relatif%20%C3%A0%20la%20qualit%C3%A9%20et%20la%20s%C3%A9curit%C3%A9%20sanitaire%20du%20sel%20alimentaire-1721388188273.pdf",
    tags: ["health", "food-safety", "nutrition", "regulation"]
  },
  {
    documentTitle: "Conserves et semi-conserves vegetales",
    lawReference: "Decret n 2-20-422",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/14/D%C3%A9cret%20n%C2%B02-20-422%20relatif%20%C3%A0%20la%20qualit%C3%A9%20et%20%C3%A0%20la%20s%C3%A9curit%C3%A9%20sanitaire%20des%20conserves-1760433529038.pdf",
    tags: ["health", "food-safety", "agriculture", "consumer"]
  },
  {
    documentTitle: "Aliments pour animaux producteurs de produits alimentaires",
    lawReference: "Decret n 2-23-557",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/15/D%C3%A9cret%20n%C2%B0%202-23-557%20relatif%20%C3%A0%20la%20qualit%C3%A9%2C%20la%20s%C3%A9curit%C3%A9%20sanitaire%20et%20l%27%C3%A9tiquetage%20des%20aliments%20pour%20animaux%20producteurs%20de%20produits%20alimentaires_%20%281%29-1765787241653.pdf",
    tags: ["health", "food-safety", "animal-feed", "agriculture"]
  },
  {
    documentTitle: "Autorite de controle des assurances et de la prevoyance sociale",
    lawReference: "Loi n 64-12",
    category: "insurance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/02/l%27Autorit%C3%A9%20de%20controle%20des%20assurances-1712070408391.pdf",
    tags: ["insurance", "regulation", "supervision", "social-protection"]
  },
  {
    documentTitle: "Commission nationale de la commande publique",
    lawReference: "Decret n 2-14-867",
    category: "public-procurement",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/05/07/la%20Commission%20nationale%20de%20la%20commande%20publique-1715071079874.pdf",
    tags: ["public-procurement", "public-contracts", "state", "governance"]
  },
  {
    documentTitle: "Qualite et securite sanitaire des vinaigres commercialises",
    lawReference: "Decret n 2-25-270",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/09/16/D%C3%A9cret%20n%C2%B0%202-25-270%20relatif%20%C3%A0%20la%20qualit%C3%A9%20et%20%C3%A0%20la%20s%C3%A9curit%C3%A9%20sanitaire%20des%20vinaigres%20commercialis%C3%A9s-1758031184418.pdf",
    tags: ["health", "food-safety", "vinegar", "consumer"]
  },
  {
    documentTitle: "Qualite et securite sanitaire des sauces commercialisees",
    lawReference: "Decret n 2-24-394",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/09/25/D%C3%A9cret%20n%C2%B0%202-24-394%20%20relatif%20%C3%A0%20la%20qualit%C3%A9%20et%20la%20s%C3%A9curit%C3%A9%20sanitaire%20des%20sauces%20commercialis%C3%A9es-1758813242675.pdf",
    tags: ["health", "food-safety", "sauces", "consumer"]
  },
  {
    documentTitle: "Qualite et securite sanitaire de certaines boissons commercialisees",
    lawReference: "Decret n 2-19-13",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/21/D%C3%A9cret%20n%C2%B0%202-19-13%20du%2017%20ramadan%201440%20%2823%20mai%202019%29%20relatif%20%C3%A0%20la%20qualit%C3%A9%20et%20la%20s%C3%A9curit%C3%A9%20sanitaire%20de%20certaines%20boissons%20commercialis%C3%A9es-1761038968111.pdf",
    tags: ["health", "food-safety", "beverages", "consumer"]
  },
  {
    documentTitle: "Code du medicament et de la pharmacie",
    lawReference: "Loi n 17-04",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/code%20du%20medicament-1711530608794.pdf",
    tags: ["health", "pharmacy", "medicine", "drugs"]
  },
  {
    documentTitle: "Assistance medicale a la procreation",
    lawReference: "Loi n 47-14",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/Assistance%20m%C3%A9dicale%20%C3%A0%20la%20procr%C3%A9ation-1711529832585.pdf",
    tags: ["health", "medical", "reproduction", "family"]
  },
  {
    documentTitle: "Securite sanitaire des produits alimentaires",
    lawReference: "Loi n 28-07",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/02/29/S%C3%A9curit%C3%A9%20sanitaire%20des%20produits%20alimentaires-1709220420232.pdf",
    tags: ["health", "food-safety", "consumer", "agriculture"]
  },
  {
    documentTitle: "Financement collaboratif",
    lawReference: "Decret n 2-21-158",
    category: "fintech",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/01/15/D%C3%A9cret%20n%20%C2%B02-21-158%20%20pris%20pour%20l%27application%20de%20la%20loi%20n%C2%B015-18%20relative%20au%20financement%20collaboratif-1768488644338.pdf",
    tags: ["fintech", "crowdfunding", "finance", "investment"]
  },
  {
    documentTitle: "Fiscalite des collectivites locales",
    lawReference: "Loi n 47-06",
    category: "local-taxation",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/09/Dahir%20n%C2%B0%201-07-195%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2047-06%20relative%20%C3%A0%20la%20fiscalit%C3%A9%20des%20collectivit%C3%A9s%20locales-1765285697377.pdf",
    tags: ["local-taxation", "tax", "territorial-collectivities", "public-finance"]
  },
  {
    documentTitle: "Centres hospitalo-universitaires",
    lawReference: "Loi n 70-13",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/11/26/Dahir%20n%C2%B0%201-16-62%20du%2017%20chaabane%201437%20%2824%20mai%202016%29%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2070-13%20relative%20aux%20centres%20hospitalo-universitaires-1764162770956.pdf",
    tags: ["health", "hospitals", "universities", "medical-system"]
  },
  {
    documentTitle: "Couverture des consequences d'evenements catastrophiques",
    lawReference: "Decret n 2-18-785",
    category: "insurance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/05/D%C3%A9cret%20n%C2%B0%202-18-785%20pris%20pour%20l%27application%20de%20la%20loi%20n%C2%B0%20110-14%20instituant%20un%20r%C3%A9gime%20de%20couverture-1764945876523.pdf",
    tags: ["insurance", "catastrophic-events", "risk", "coverage"]
  },
  {
    documentTitle: "Servitudes de balisage aux abords des aerodromes",
    lawReference: "Decret n 2-23-919",
    category: "aviation",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/08/01/d%C3%A9cret%20n%202.23.919%20relatif%20aux%20servitudes%20de%20balisage%20institu%C3%A9es%20aux%20abords%20des%20a%C3%A9rodromes%20et%20le%20long%20des%20routes%20a%C3%A9riennes-1722519138366.pdf",
    tags: ["aviation", "airports", "air-navigation", "transport"]
  },
  {
    documentTitle: "Organisation et fonctionnement des etablissements penitentiaires",
    lawReference: "Decret n 2-00-485",
    category: "prisons",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/01/20/D%C3%A9cret%20n%C2%B0%202-00-485%20fixant%20les%20modalit%C3%A9s%20d%E2%80%99application%20de%20la%20loi%20n%C2%B0%2023-98%20relative%20%C3%A0%20l%E2%80%99organisation%20et%20au%20fonctionnement%20des%20%C3%A9tablissements%20p%C3%A9nitentiaires-1737371776223.pdf",
    tags: ["prisons", "criminal", "detention", "penitentiary"]
  },
  {
    documentTitle: "Repression des fraudes sur les marchandises",
    lawReference: "Loi n 13-83",
    category: "market-regulation",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/12/LOI%20RELATIVE%20A%20LA%20REPRESSION%20DES%20FRAUDES%20SUR%20LES%20MARCHANDISES-1710253952288.pdf",
    tags: ["market-regulation", "fraud", "consumer", "commercial"]
  },
  {
    documentTitle: "Loi relative a l'eau",
    lawReference: "Loi n 36-15",
    category: "environment",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/11/27/dahir%20n%C2%B0%201-16-113%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2036-15%20relative%20%C3%A0%20l%27eau-1764255109367.pdf",
    tags: ["environment", "water", "natural-resources", "public-policy"]
  },
  {
    documentTitle: "Police de l'environnement",
    lawReference: "Decret n 2-14-782",
    category: "environment",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/02/23/D%C3%A9cret%20n%C2%B0%202-14-782%20relatif%20%C3%A0%20l%27organisation%20et%20aux%20modalit%C3%A9s%20de%20fonctionnement%20de%20la%20police%20de%20l%27environnement-1771846612074.pdf",
    tags: ["environment", "inspection", "compliance", "enforcement"]
  },
  {
    documentTitle: "Charte nationale de l'environnement et du developpement durable",
    lawReference: "Loi-cadre n 99-12",
    category: "environment",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/29/charte%20nationale%20de%20l%27environnement%20et%20du%20d%C3%A9veloppement%20durable-1711711668038.pdf",
    tags: ["environment", "sustainability", "development", "framework-law"]
  },
  {
    documentTitle: "L'evaluation environnementale",
    lawReference: "Loi n 49-17",
    category: "environment",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/25/L%27%C3%A9valuation%20environnementale-1711367038562.pdf",
    tags: ["environment", "impact-assessment", "projects", "compliance"]
  },
  {
    documentTitle: "La lutte contre la pollution de l'air",
    lawReference: "Loi n 13-03",
    category: "environment",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/30/la%20lutte%20contre%20la%20pollution%20de%20l%27air-1714463870018.pdf",
    tags: ["environment", "air-quality", "pollution", "public-health"]
  },
  {
    documentTitle: "Autoproduction de l'energie electrique",
    lawReference: "Loi n 82-21",
    category: "energy",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/06/18/Dahir%20n%C2%BA%201-23-21%20du%2019%20rejeb%201444%20%2810%20f%C3%A9vrier%202023%29%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2082-21%20relative%20%C3%A0%20l%27autoproduction%20de%20l%27%C3%A9nergie%20%C3%A9lectrique-1750255552226.pdf",
    tags: ["energy", "electricity", "autoproduction", "renewables"]
  },
  {
    documentTitle: "Contrats de partenariat public-prive",
    lawReference: "Loi n 86-12",
    category: "administrative-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/22/Contrats%20de%20partenariat%20public-priv%C3%A9.-1711116147843.pdf",
    tags: ["administrative-governance", "public-private-partnership", "contracts", "public-sector"]
  },
  {
    documentTitle: "Simplification des procedures et des formalites administratives",
    lawReference: "Loi n 55-19",
    category: "administrative-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/26/Simplification%20des%20proc%C3%A9dures%20et%20des%20formalit%C3%A9s%20administratives-1711452470803.pdf",
    tags: ["administrative-governance", "administration", "procedures", "formalities"]
  },
  {
    documentTitle: "Communes",
    lawReference: "Loi organique n 113-14",
    category: "territorial-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/11/21/loi%20relative%20aux%20communes-1763719420523.pdf",
    tags: ["territorial-governance", "communes", "local-government", "administration"]
  },
  {
    documentTitle: "Prefectures et provinces",
    lawReference: "Loi organique n 112-14",
    category: "territorial-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/29/Pr%C3%A9fectures%20et%20provinces-1711720789916.pdf",
    tags: ["territorial-governance", "prefectures", "provinces", "local-government"]
  },
  {
    documentTitle: "Loi organique relative a la loi de finances",
    lawReference: "Loi organique n 130-13",
    category: "public-finance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/29/loi%20organique%20n%C2%B0%20130-13%20relative%20%C3%A0%20la%20loi%20de%20finances-1711720944297.pdf",
    tags: ["public-finance", "budget", "finance-law", "state-finance"]
  },
  {
    documentTitle: "Charte des services publics",
    lawReference: "Loi n 54-19",
    category: "administrative-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/25/Dahir%20n%C2%B0%201-21-58%20%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2054-19%20portant%20Charte%20des%20services%20publics-1766668617511.pdf",
    tags: ["administrative-governance", "public-services", "citizens", "administration"]
  },
  {
    documentTitle: "Loi-cadre relative au systeme d'education, de formation et de recherche scientifique",
    lawReference: "Loi-cadre n 51-17",
    category: "education",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/13/loi-cadre%20relative%20au%20systeme%20d%E2%80%99education%2C%20de%20formation%20et%20de%20recherche%20scientifique-1710330161327.pdf",
    tags: ["education", "training", "research", "universities"]
  },
  {
    documentTitle: "Statut des journalistes professionnels",
    lawReference: "Loi n 89-13",
    category: "journalism",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/Statut%20des%20journalistes%20professionels-1711531873789.pdf",
    tags: ["journalism", "media", "press", "profession"]
  },
  {
    documentTitle: "La presse et l'edition",
    lawReference: "Loi n 88-13",
    category: "journalism",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/La%20presse%20et%20%C3%A0%20l%27%C3%A9dition-1711531925401.pdf",
    tags: ["journalism", "press", "publishing", "media"]
  },
  {
    documentTitle: "Droits d'auteur et droits voisins",
    lawReference: "Loi n 2-00",
    category: "culture",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/13/Droits%20d%27auteur%20et%20droits%20voisins-1734085207226.pdf",
    tags: ["culture", "copyright", "authors", "intellectual-property"]
  },
  {
    documentTitle: "Service militaire",
    lawReference: "Loi n 44-18",
    category: "security",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/22/dahir%20n%C2%B0%201-19-03%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2044-18%20relative%20au%20service%20militaire-1761130206928.pdf",
    tags: ["security", "military", "national-service", "defense"]
  },
  {
    documentTitle: "Application de la loi relative au service militaire",
    lawReference: "Decret n 2-19-46",
    category: "security",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/20/D%C3%A9cret%20n%C2%B0%202-19-46%20fixant%20les%20modalit%C3%A9s%20d%27application%20de%20la%20loi%20n%C2%B0%2044-18%20relative%20au%20service%20militaire-1760951909253.pdf",
    tags: ["security", "military", "decree", "defense"]
  },
  {
    documentTitle: "Etablissements touristiques et autres formes d'hebergement touristique",
    lawReference: "Loi n 80-14",
    category: "tourism",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/15/Etablissements%20touristiques%20et%20autres%20formes%20d%27h%C3%A9bergement%20touristiquehebergement%20touristique-1710511032714.pdf",
    tags: ["tourism", "hospitality", "hotels", "accommodation"]
  },
  {
    documentTitle: "Peche illicite, non declaree et non reglementee",
    lawReference: "Decret n 2-17-455",
    category: "fisheries",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/11/07/D%C3%A9cret%20n%C2%B0%202-17-455%20pris%20pour%20l%27application%20de%20certaines%20dispositions%20du%20titre%20I%20de%20la%20loi%20n%C2%B0%2015-12%20relative%20%C3%A0%20la%20pr%C3%A9vention%20et%20la%20lutte%20contre%20l-1762503514273.pdf",
    tags: ["fisheries", "maritime", "compliance", "sea-food"]
  },
  {
    documentTitle: "Reglementation des produits explosifs a usage civil",
    lawReference: "Loi n 22-16",
    category: "industrial-safety",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/01/R%C3%A9glementation%20des%20produits%20explosifs%20%C3%A0%20usage%20civil-1711969644437.pdf",
    tags: ["industrial-safety", "explosives", "mines", "civil-security"]
  },
  {
    documentTitle: "Performances energetiques minimales des equipements",
    lawReference: "Decret n 2-20-716",
    category: "energy-efficiency",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/02/23/D%C3%A9cret%20n%C2%B0%202-20-716%20%20relatif%20aux%20performances%20%C3%A9nerg%C3%A9tiques%20minimales%20des%20appareils%20et%20%C3%A9quipements%20fonctionnant%20%C3%A0%20l%27%C3%A9lectricit%C3%A9%2C%20au%20gaz%20naturel%2C%20a-1771843546776.pdf",
    tags: ["energy-efficiency", "energy", "equipment", "standards"]
  },
  {
    documentTitle: "Loi relative au commerce exterieur",
    lawReference: "Loi n 13-89",
    category: "foreign-trade",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/15/loi%20relative%20au%20commerce%20exterieur-1710512028545.pdf",
    tags: ["foreign-trade", "commercial", "imports", "exports"]
  },
  {
    documentTitle: "Loi-cadre portant reforme fiscale",
    lawReference: "Loi-cadre n 69-19",
    category: "tax",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/03/02/Loi-cadre%20portant%20r%C3%A9forme%20fiscale%20%281%29Dahir%20n%C2%BA%201-21-86%20%20portant%20promulgation%20de%20la%20loi-cadre%20n%C2%B0%2069-19%20portant%20r%C3%A9forme%20fiscale_-1772461164747.pdf",
    tags: ["tax", "public-finance", "fiscal-reform", "state-finance"]
  },
  {
    documentTitle: "Agence nationale de gestion strategique des participations de l'Etat",
    lawReference: "Loi n 82-20",
    category: "public-enterprises",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/04/02/Dahir%20n%C2%B0%201-21-96%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2082-20%20portant%20cr%C3%A9ation%20de%20l%27Agence%20nationale-1775129751027.pdf",
    tags: ["public-enterprises", "state-participations", "governance", "investment"]
  },
  {
    documentTitle: "Microfinance",
    lawReference: "Loi n 50-20",
    category: "microfinance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/13/LOI%20RELATIVE%20A%20LA%20MICROFINANCE-1710331833101.pdf",
    tags: ["microfinance", "banking", "financial-inclusion", "credit"]
  },
  {
    documentTitle: "Transformation de la Caisse centrale de garantie en societe anonyme",
    lawReference: "Loi n 36-20",
    category: "public-enterprises",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/26/Transformation%20de%20la%20Caisse%20centrale%20de%20garantie%20en%20soci%C3%A9t%C3%A9%20anonyme-1711463464386.pdf",
    tags: ["public-enterprises", "guarantee", "finance", "banking"]
  },
  {
    documentTitle: "Reorganisation de Casablanca Finance City",
    lawReference: "Decret-loi n 2-20-665",
    category: "financial-market",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/20/Reorganisation%20de%20casablanca%20finance%20city-1710936103755.pdf",
    tags: ["financial-market", "casablanca-finance-city", "investment", "banking"]
  },
  {
    documentTitle: "Reforme des etablissements et entreprises publics",
    lawReference: "Loi-cadre n 50-21",
    category: "public-enterprises",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/20/loi-cadre%20relative%20a%20la%20reforme%20des%20%C3%A9tablissements%20et%20enterprises%20publics-1710935703289.pdf",
    tags: ["public-enterprises", "governance", "reform", "public-sector"]
  },
  {
    documentTitle: "Dispositions relatives au pret de titres",
    lawReference: "Loi n 45-12",
    category: "financial-market",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/pr%C3%AAt%20de%20titres-1711550347692.pdf",
    tags: ["financial-market", "securities", "lending", "capital-markets"]
  },
  {
    documentTitle: "Fonds Mohammed VI pour l'Investissement",
    lawReference: "Loi n 76-20",
    category: "investment",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/15/LOI%20PORTANT%20CREATION%20DU%20%C2%AB%20FONDS%20MOHAMMED%20VI%20POUR%20L%E2%80%99INVESTISSEMENT%20%C2%BB-1710511203248.pdf",
    tags: ["investment", "fund", "public-finance", "development"]
  },
  {
    documentTitle: "Controle de l'exportation et de l'importation des biens a double usage",
    lawReference: "Loi n 42-18",
    category: "foreign-trade",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/22/contr%C3%B4le%20de%20l%27exportation-1711116497013.pdf",
    tags: ["foreign-trade", "dual-use-goods", "security", "customs"]
  },
  {
    documentTitle: "Commercialisation directe des fruits et legumes issus de l'agregation agricole",
    lawReference: "Loi n 37-21",
    category: "agriculture",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/09/24/Dahir%20n%C2%B0%201-21-72%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2037-21%20%C3%A9dictant%20des%20mesures%20particuli%C3%A8res%20relatives%20%C3%A0%20la%20commercialisation%20directe%20des%20fruits%20-1758703193643.pdf",
    tags: ["agriculture", "commercialization", "fruit", "vegetables"]
  },
  {
    documentTitle: "Profession d'agent de voyages",
    lawReference: "Loi n 11-16",
    category: "regulated-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/21/Dahir%20n%C2%B01-18-107%20portant%20promulgation%20de%20la%20loi%20n%C2%B011-16%20r%C3%A9glementant%20la%20profession%20d%27agent%20de%20voyages-1761040428707.pdf",
    tags: ["regulated-professions", "tourism", "travel-agency", "services"]
  },
  {
    documentTitle: "Profession d'ingenieur geometre-topographe",
    lawReference: "Loi n 30-93",
    category: "regulated-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/l%27exercice%20de%20la%20profession%20d%27ing%C3%A9nieur%20g%C3%A9om%C3%A8tre-%20topographe-1711541874179.pdf",
    tags: ["regulated-professions", "surveyors", "real-estate", "topography"]
  },
  {
    documentTitle: "Exercice de la profession de sage-femme",
    lawReference: "Loi n 44-13",
    category: "regulated-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/05/07/Exercice%20de%20la%20profession%20de%20sage-femme-1715070679904.pdf",
    tags: ["regulated-professions", "health", "midwife", "medical"]
  },
  {
    documentTitle: "Artiste et metiers artistiques",
    lawReference: "Loi n 68-16",
    category: "culture",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/Artiste%20et%20m%C3%A9tiers%20artistiques-1711542119027.pdf",
    tags: ["culture", "artists", "regulated-professions", "creative-work"]
  },
  {
    documentTitle: "Exercice des professions infirmieres",
    lawReference: "Loi n 43-13",
    category: "regulated-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/EXERCICE%20DES%20PROFESSIONS%20INFIRMIERES-1711542188326.pdf",
    tags: ["regulated-professions", "health", "nursing", "medical"]
  },
  {
    documentTitle: "Professions de reeducation, readaptation et rehabilitation fonctionnelle",
    lawReference: "Loi n 45-13",
    category: "regulated-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/23/Dahir%20n%C2%BA%201-19-119%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2045-13%20relative%20%C3%A0%20l%27exercice%20des%20professions%20de%20r%C3%A9%C3%A9ducation-1761208890894.pdf",
    tags: ["regulated-professions", "health", "rehabilitation", "medical"]
  },
  {
    documentTitle: "Ordre national des medecins",
    lawReference: "Loi n 08-12",
    category: "regulated-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/28/Ordre%20national%20des%20m%C3%A9decins-1711636649586.pdf",
    tags: ["regulated-professions", "health", "doctors", "medical"]
  },
  {
    documentTitle: "Organisation professionnelle des comptables agrees",
    lawReference: "Loi n 127-12",
    category: "regulated-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/Organisation%20professionnelle%20des%20comptables%20agr%C3%A9%C3%A9s-1711542436982.pdf",
    tags: ["regulated-professions", "accounting", "business", "finance"]
  },
  {
    documentTitle: "Profession d'Adoul",
    lawReference: "Loi n 16-03",
    category: "judicial-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/05/professions%20d%27adoul-1709637192726.pdf",
    tags: ["judicial-professions", "adoul", "notarial", "justice"]
  },
  {
    documentTitle: "Profession d'huissier de justice",
    lawReference: "Loi n 81-03",
    category: "judicial-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/05/Huissier%20de%20justice-1709637637842.pdf",
    tags: ["judicial-professions", "bailiff", "justice", "procedure"]
  },
  {
    documentTitle: "Traducteurs agrees pres les juridictions",
    lawReference: "Loi n 50-00",
    category: "judicial-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/05/Traducteurs%20Agr%C3%A9%C3%A9s%20Pr%C3%A8s%20Les%20Juridictions-1709638316334.pdf",
    tags: ["judicial-professions", "translation", "courts", "justice"]
  },
  {
    documentTitle: "Experts judiciaires",
    lawReference: "Loi n 45-00",
    category: "judicial-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/03/02/Dahir%20n%C2%B0%201-01-126%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2045-00%20relatives%20aux%20experts%20judiciaires-1772449613422.pdf",
    tags: ["judicial-professions", "experts", "courts", "justice"]
  },
  {
    documentTitle: "Services de la navigation aerienne",
    lawReference: "Decret n 2-23-918",
    category: "aviation",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/02/03/D%C3%A9cret%20relatif%20aux%20services%20de%20la%20navigation%20a%C3%A9rienne-1738592624446.pdf",
    tags: ["aviation", "air-navigation", "transport", "air-safety"]
  },
  {
    documentTitle: "Immatriculation et vente forcee des aeronefs",
    lawReference: "Decret n 2-23-921",
    category: "aviation",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/07/19/DCRETR~1.PDF-1721387256694.pdf",
    tags: ["aviation", "aircraft", "registration", "secured-transactions"]
  },
  {
    documentTitle: "Conception, production, maintenance et navigabilite des aeronefs",
    lawReference: "Decret n 2-23-920",
    category: "aviation",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/07/19/D%C3%A9cret%20relatif%20%C3%A0%20la%20conception%2C%20%C3%A0%20la%20production%2C%20%C3%A0%20la%20maintenance%20et%20%C3%A0%20la%20navigabilit%C3%A9%20des%20a%C3%A9ronefs-1721387499264.pdf",
    tags: ["aviation", "aircraft", "maintenance", "air-safety"]
  },
  {
    documentTitle: "Prevention et lutte contre le peril animalier dans les aerodromes",
    lawReference: "Decret n 2-23-319",
    category: "aviation",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/07/22/D%C3%A9cret%20relatif%20%C3%A0%20la%20pr%C3%A9vention%20et%20%C3%A0%20la%20lutte%20contre%20le%20p%C3%A9ril%20animalier%20d...-1721659456558.pdf",
    tags: ["aviation", "airports", "wildlife-risk", "air-safety"]
  },
  {
    documentTitle: "Groupements d'interet public",
    lawReference: "Loi n 08-00",
    category: "administrative-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/Groupements%20d%E2%80%99int%C3%A9r%C3%AAt%20public-1711529585338.pdf",
    tags: ["administrative-governance", "public-interest", "public-sector", "cooperation"]
  },
  {
    documentTitle: "Regions",
    lawReference: "Loi organique n 111-14",
    category: "territorial-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/R%C3%A9gions-1711547940788.pdf",
    tags: ["territorial-governance", "regions", "local-government", "administration"]
  },
  {
    documentTitle: "Registre National Agricole",
    lawReference: "Loi n 80-21",
    category: "agriculture",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/04/01/Registre%20National%20Agricole-1711983042902.pdf",
    tags: ["agriculture", "registry", "farmers", "digital-administration"]
  },
  {
    documentTitle: "Operations de pension",
    lawReference: "Loi n 24-01",
    category: "financial-market",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/22/Dahir%20n%C2%B0%201-04-04%20du%2021%20avril%202004%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2024-01%20relative%20aux%20op%C3%A9rations%20de%20pension%2C%20tel%20que%20modifi%C3%A9%20et%20compl%C3%A9t%C3%A9-1745316532056.pdf",
    tags: ["financial-market", "repo-transactions", "securities", "banking"]
  },
  {
    documentTitle: "Titres de creances negociables",
    lawReference: "Loi n 35-94",
    category: "financial-market",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/23/Dahir%20n%C2%B01-95-3%20du%2026%20janvier%201995%20portant%20promulgation%20de%20la%20loi%20n%C2%B035-94%20relative%20%C3%A0%20certains%20titres%20de%20cr%C3%A9ances%20n%C3%A9gociables%2C%20tel%20que%20modifi%C3%A9%20et-1745421385874.pdf",
    tags: ["financial-market", "debt-securities", "capital-markets", "finance"]
  },
  {
    documentTitle: "Obligations securisees",
    lawReference: "Loi n 94-21",
    category: "financial-market",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/02/03/obligations%20s%C3%A9curis%C3%A9es-1738593011155.pdf",
    tags: ["financial-market", "covered-bonds", "banking", "real-estate-finance"]
  },
  {
    documentTitle: "Titrisation des actifs",
    lawReference: "Loi n 33-06",
    category: "financial-market",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/23/Dahir%20n%C2%B0%201-08-95%20du%2020%20du%2020%20octobre%202008%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2033-06%20relative%20%C3%A0%20la%20titrisation%20des%20actifs%2C%20tel%20que%20modifi%C3%A9%20et%20compl%C3%A9t%C3%A9-1745421190170.pdf",
    tags: ["financial-market", "securitization", "capital-markets", "finance"]
  },
  {
    documentTitle: "Agence nationale des equipements publics",
    lawReference: "Loi n 48-17",
    category: "public-enterprises",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/20/Dahir%20n%C2%B0%201-19-83%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2048-17%20portant%20cr%C3%A9ation%20de%20l%27Agence%20nationale%20des%20%C3%A9quipements%20publics-1760950216823.pdf",
    tags: ["public-enterprises", "public-equipment", "infrastructure", "administration"]
  },
  {
    documentTitle: "Dispositifs medicaux",
    lawReference: "Loi n 84-12",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/dispositifs%20m%C3%A9dicaux-1711529908455.pdf",
    tags: ["health", "medical-devices", "medical", "regulation"]
  },
  {
    documentTitle: "Usages licites du cannabis",
    lawReference: "Loi n 13-21",
    category: "health",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/25/usages%20licites%20du%20cannabis-1711358998943.pdf",
    tags: ["health", "cannabis", "agriculture", "regulated-products"]
  },
  {
    documentTitle: "Surete et securite nucleaires et radiologiques",
    lawReference: "Loi n 142-12",
    category: "nuclear-safety",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/25/La%20s%C3%BBret%C3%A9%20et%20la%20s%C3%A9curit%C3%A9%20nucl%C3%A9aires%20et%20radiologiques-1711367322031.pdf",
    tags: ["nuclear-safety", "radiological-safety", "health", "security"]
  },
  {
    documentTitle: "Exercice de la medecine",
    lawReference: "Loi n 131-13",
    category: "regulated-professions",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/22/la%20loi%20relative%20%C3%A0%20l%27exercice%20de%20la%20m%C3%A9decine-1711117195702.pdf",
    tags: ["regulated-professions", "medicine", "health", "doctors"]
  },
  {
    documentTitle: "Protection et promotion des droits des personnes en situation de handicap",
    lawReference: "Loi-cadre n 97-13",
    category: "disability-rights",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/protection%20et%20%C3%A0%20la%20promotion%20des%20droits%20des%20personnes%20en%20situation%20de%20handicap-1711530092654.pdf",
    tags: ["disability-rights", "rights-liberties", "social-protection", "accessibility"]
  },
  {
    documentTitle: "Police sanitaire veterinaire a l'importation",
    lawReference: "Loi n 24-89",
    category: "veterinary",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/26/les%20mesures%20de%20police%20sanitaire%20v%C3%A9t%C3%A9rinaire%20%C3%A0%20l%27importation%20d%27animaux-1711452410793.pdf",
    tags: ["veterinary", "animals", "food-safety", "imports"]
  },
  {
    documentTitle: "Inspection sanitaire des animaux et denrees animales",
    lawReference: "Loi n 49-99",
    category: "veterinary",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/les%20mesures%20de%20l%27inspection%20sanitaire%20et%20qualitative%20des%20animaux-1711530963101.pdf",
    tags: ["veterinary", "food-safety", "inspection", "animals"]
  },
  {
    documentTitle: "Protection sociale",
    lawReference: "Loi-cadre n 09-21",
    category: "social-protection",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/02/24/Dahir%20n%C2%BA%201-21-30%20%20portant%20promulgation%20de%20la%20loi-cadre%20n%C2%BA%2009-21%20relative%20%C3%A0%20la%20protection%20sociale_-1771931229716.pdf",
    tags: ["social-protection", "health-insurance", "pensions", "public-policy"]
  },
  {
    documentTitle: "Application de la taxe sur la valeur ajoutee",
    lawReference: "Decret n 2-06-574",
    category: "tax",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/01/26/D%C3%A9cret%20n%C2%B0%202-06-574%20pris%20pour%20l%27application%20de%20la%20taxe-1769436554964.pdf",
    tags: ["tax", "vat", "public-finance", "business"]
  },
  {
    documentTitle: "Delais de paiement et interets moratoires des commandes publiques",
    lawReference: "Decret n 2-16-344",
    category: "public-procurement",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/11/26/D%C3%A9cret%20n%C2%B02-16-344%20fixant%20les%20d%C3%A9lais%20de%20paiement%20et%20les%20int%C3%A9r%C3%AAts%20moratoires%20relatifs%20aux%20commandes%20publiques.docx-1764149518445.pdf",
    tags: ["public-procurement", "payment-deadlines", "public-contracts", "finance"]
  },
  {
    documentTitle: "Nantissement des marches publics",
    lawReference: "Loi n 112-13",
    category: "public-procurement",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/29/Nantissement%20des%20march%C3%A9s%20publics-1711721063947.pdf",
    tags: ["public-procurement", "pledge", "public-contracts", "credit"]
  },
  {
    documentTitle: "Conseil national des archives",
    lawReference: "Decret n 2-17-384",
    category: "archives",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/22/Conseil%20national%20des%20archives-1711116060176.pdf",
    tags: ["archives", "administrative-governance", "public-records", "culture"]
  },
  {
    documentTitle: "Commission nationale du partenariat public-prive",
    lawReference: "Decret n 2-15-45",
    category: "administrative-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/03/27/La%20Commission%20nationale%20du%20partenariat%20public-priv%C3%A9-1711549717381.pdf",
    tags: ["administrative-governance", "public-private-partnership", "public-sector", "contracts"]
  },
  {
    documentTitle: "Application des titres de creances negociables",
    lawReference: "Decret n 2-94-651",
    category: "financial-market",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/22/D%C3%A9cret%20n%C2%B02-94-651%20du%205%20juillet%201995%20pris%20pour%20l%27application%20de%20la%20loi%20n%C2%B0%2035-94%20relative%20%C3%A0%20certains%20titres%20de%20cr%C3%A9ances%20n%C3%A9gociables%20%281%29-1745318143835.pdf",
    tags: ["financial-market", "debt-securities", "capital-markets", "decree"]
  },
  {
    documentTitle: "Application du code des assurances - livre II titre IV",
    lawReference: "Decret n 2-20-372",
    category: "insurance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/13/D%C3%A9cret%20n%C2%B0%202-20-372%20du%2010%20rabii%20II%201442%20%2826%20novembre%202020%29-1760354502642.pdf",
    tags: ["insurance", "code-des-assurances", "regulation", "decree"]
  },
  {
    documentTitle: "Concession de gaz naturel Sidi Al Harati Nord",
    lawReference: "Decret n 2-23-671",
    category: "hydrocarbons",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/19/D%C3%A9cret%20n%C2%B0%202-23-671%20%20la%20concession%20d%27exploitation-1766133871988.pdf",
    tags: ["hydrocarbons", "natural-gas", "mines", "energy"]
  },
  {
    documentTitle: "Fondation Maroc 2030",
    lawReference: "Loi n 35-25",
    category: "sports-events",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/02/dahir%20n%C2%B01-25-54%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2035-25%20portant%20cr%C3%A9ation%20de%20la%20%C2%AB%20Fondation%20Maroc%202030%C2%BB_-1764669965080.pdf",
    tags: ["sports-events", "public-governance", "foundation", "development"]
  },
  {
    documentTitle: "Reorganisation de l'Ecole Hassania des travaux publics",
    lawReference: "Loi n 39-13",
    category: "education",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/24/Dahir%20n%C2%B0%201-18-71%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2039-13%20relative%20%C3%A0%20la%20r%C3%A9organisation%20de%20l%27Ecole%20Hassania-1761299594621.pdf",
    tags: ["education", "public-works", "engineering", "schools"]
  },
  {
    documentTitle: "Appui du Fonds de modernisation de l'administration publique",
    lawReference: "Decret n 2-23-245",
    category: "administrative-governance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/11/D%C3%A9cret%20n%C2%B0%202-23-245%20fixant%20les%20formes%20et%20modalit%C3%A9s%20du%20versement%20et%20d%27octroi%20de%20l%27appui%20du%20Fonds%20de%20modernisation%20de%20l%27administration%20publique%2C%20d%27-1765448221465.pdf",
    tags: ["administrative-governance", "digital-transformation", "public-sector", "public-finance"]
  },
  {
    documentTitle: "Montant maximum du micro-credit et limites des fonds recus",
    lawReference: "Decret n 2-25-450",
    category: "microfinance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/12/01/D%C3%A9cret%20n%C2%B02-25-450%20fixant%20le%20montant%20maximum%20du%20micro-cr%C3%A9dit%20et%20les%20limites%20des%20fonds%20re%C3%A7us%20par%20les%20institutions%20de%20microfinance-1764583648355.pdf",
    tags: ["microfinance", "credit", "financial-inclusion", "banking"]
  },
  {
    documentTitle: "Rationalisation des subventions directes de l'Etat en matiere d'investissement",
    lawReference: "Decret n 2-22-234",
    category: "investment",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/02/09/D%C3%A9cret%20n%C2%B0%202-22-234%20pris%20pour%20l%27application%20des%20dispositions%20de%20l%27article%207%20de%20la%20loi%20de%20finances%20n%C2%B0%2076-21%20pour%20l%27ann%C3%A9e%20budg%C3%A9taire%202022%2C%20relatif%20-1770645619607.pdf",
    tags: ["investment", "subsidies", "public-finance", "state-support"]
  },
  {
    documentTitle: "Garantie de l'Etat en couverture de la liquidite d'urgence",
    lawReference: "Decret n 2-22-925",
    category: "banking",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/01/09/D%C3%A9cret%20n%C2%B0%202-22-925%20-1767967454181.pdf",
    tags: ["banking", "state-guarantee", "liquidity", "financial-stability"]
  },
  {
    documentTitle: "Fonctionnement du Comite des etablissements de credit",
    lawReference: "Decret n 2-17-30",
    category: "banking",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/18/D%C3%A9cret%20n%C2%B0%202-17-30%20du%2023%20hija%201438%28%2014%20septembre%202017%29%20fixant%20les%20modalit%C3%A9s%20de%20fonctionnement%20du%20Comit%C3%A9%20des%20%C3%A9tablissements%20de%20cr%C3%A9dit_-1744973083482.pdf",
    tags: ["banking", "credit-institutions", "committee", "financial-regulation"]
  },
  {
    documentTitle: "Conseil national du credit et de l'epargne",
    lawReference: "Decret n 2-17-31",
    category: "banking",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/18/D%C3%A9cret%20n%C2%B0%202-17-31%20du6%20moharrem1439%20%2827%20septembre%202017%29%20fixant%20la%20composition%20et%20les%20modalit%C3%A9s%20de%20fonctionnement%20du%20conseil%20national%20du%20cr%C3%A9dit%20e-1744972938751.pdf",
    tags: ["banking", "credit", "savings", "financial-regulation"]
  },
  {
    documentTitle: "Comite de coordination et de surveillance des risques systemiques",
    lawReference: "Decret n 2-17-32",
    category: "banking",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/04/18/D%C3%A9cret%20n%C2%B02-17-32%20du%2014%20septembre%202017%20fixant%20la%20composition%20et%20les%20modalit%C3%A9s%20de%20fonctionnement%20du%20comit%C3%A9%20de%20coordination%20et%20de%20surveillance%20des-1744972599169.pdf",
    tags: ["banking", "systemic-risk", "financial-stability", "supervision"]
  },
  {
    documentTitle: "Taxe de solidarite contre les evenements catastrophiques",
    lawReference: "Decret n 2-19-244",
    category: "insurance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/17/d%C3%A9cret%20n%C2%B0%202-19-244%20instituant%20au%20profit%20du%20fonds%20de%20solidarit%C3%A9%20contre%20les%20%C3%A9v%C3%A9nement%20catastrophiques%20une%20taxe%20parafiscale%20d%C3%A9nom%C3%A9e%20taxe%20de%20solidar-1760708722244.pdf",
    tags: ["insurance", "catastrophic-events", "tax", "solidarity-fund"]
  },
  {
    documentTitle: "Depenses effectuees par le ministere de la sante",
    lawReference: "Decret n 2-20-270",
    category: "public-finance",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/15/D%C3%A9cret%20n%C2%B02-20-270%20relatif%20aux%20modalit%C3%A9s%20d%27ex%C3%A9cution%20des%20d%C3%A9penses%20effectu%C3%A9es%20par%20le%20minist%C3%A8re%20de%20la%20sant%C3%A9.docx-1760520711151.pdf",
    tags: ["public-finance", "health", "public-spending", "administration"]
  },
  {
    documentTitle: "Horaires de travail dans les administrations publiques au port",
    lawReference: "Decret n 2-15-304",
    category: "ports",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/11/28/D%C3%A9cret%20n%C2%B02-15-304%20pdf-1764325836357.pdf",
    tags: ["ports", "labor", "public-administration", "transport"]
  },
  {
    documentTitle: "Soldes et avantages du service militaire",
    lawReference: "Decret n 2-19-47",
    category: "security",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/10/22/D%C3%A9cret%20n%C2%B0%202-19-47%20fixant%20les%20soldes%20et%20les%20avantages%20allou%C3%A9s%20aux%20appel%C3%A9s%20accomplissant%20le%20service%20militaire%20et%20aux%20r%C3%A9servistes%20rappel%C3%A9s-1761125272342.pdf",
    tags: ["security", "military", "compensation", "public-finance"]
  },
  {
    documentTitle: "Organisation judiciaire 1913",
    lawReference: "Dahir 1913",
    category: "judicial-organization",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/09/26/L%27organisation%20judiciaire%201913-1727362603671.pdf",
    tags: ["judicial-organization", "courts", "justice", "historical"]
  },
  {
    documentTitle: "Juridictions de proximite",
    lawReference: "Loi n 42-10",
    category: "judicial-organization",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/30/Dahir%20n%C2%B0%201-11-151%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2042-10%20portant%20organi.._-1735569015483.pdf",
    tags: ["judicial-organization", "local-courts", "justice", "procedure"]
  },
  {
    documentTitle: "Tribunaux administratifs",
    lawReference: "Loi n 41-90",
    category: "judicial-organization",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/30/Dahir%20n%C2%B0%201-91-225%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2041-90%20instituant%20des.._-1735569047429.pdf",
    tags: ["judicial-organization", "administrative-courts", "public-law", "justice"]
  },
  {
    documentTitle: "Juridictions de commerce",
    lawReference: "Loi n 53-95",
    category: "judicial-organization",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/30/dahir%20n%C2%B0%201-97-65%20portant%20romulgation%20de%20la%20loi%20n%C2%B0%2053-95%20instituant%20des%20j.._-1735568793344.pdf",
    tags: ["judicial-organization", "commercial-courts", "commercial", "justice"]
  },
  {
    documentTitle: "Cours d'appel administratives",
    lawReference: "Loi n 80-03",
    category: "judicial-organization",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2024/12/30/Dahir%20n%C2%B0%201-06-07%20portant%20promulgation%20de%20la%20loi%20n%C2%B0%2080-03%20instituant%20des%20.._-1735568967906.pdf",
    tags: ["judicial-organization", "administrative-appeal-courts", "public-law", "justice"]
  },
  {
    documentTitle: "Application de l'article 31 de la loi relative a l'AMMC",
    lawReference: "Decret n 2-17-216",
    category: "financial-market",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2025/11/11/D%C3%A9cret%20n%C2%B02-17-216%20pdf-1762871527655.pdf",
    tags: ["financial-market", "ammc", "capital-markets", "supervision"]
  },
  {
    documentTitle: "Documentation des prix de transfert",
    lawReference: "Decret n 2-22-1020",
    category: "tax",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/02/23/D%C3%A9cret%20n%C2%B0%202-22-1020%20%20fixant%20la%20liste%20et%20les%20modalit%C3%A9s%20de%20communication%20de%20la%20documentation%20des%20prix%20de%20transfert%20%C3%A0%20l%27administration%20fiscale_-1771843036380.pdf",
    tags: ["tax", "transfer-pricing", "documentation", "business"]
  },
  {
    documentTitle: "Fermes aquacoles",
    lawReference: "Decret n 2-24-830",
    category: "aquaculture",
    sourceName: "Adala - Ministere de la Justice",
    sourceUrl:
      "https://adala.justice.gov.ma/api/uploads/2026/02/12/D%C3%A9cret%20n%C2%B0%202-24-830%20%20relatif%20aux%20fermes%20aquacoles-1770908260356.pdf",
    tags: ["aquaculture", "fisheries", "maritime", "agriculture"]
  },
  {
    documentTitle: "Bulletin officiel n 7465-bis - Loi de finances 2026",
    lawReference: "BO n 7465-bis / Loi n 50-25",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7465-bis_fr.pdf",
    tags: ["official-bulletin", "public-finance", "tax", "budget", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7466 - Textes generaux",
    lawReference: "BO n 7466",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7466_fr.pdf",
    tags: ["official-bulletin", "public-finance", "administration", "2025", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7484 - Textes generaux",
    lawReference: "BO n 7484",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7484_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7488 - Textes generaux",
    lawReference: "BO n 7488",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7488_Fr.pdf",
    tags: ["official-bulletin", "education", "customs", "tax", "health", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7506 - Textes generaux",
    lawReference: "BO n 7506",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7506_fr.pdf",
    tags: ["official-bulletin", "transport", "energy", "food-safety", "aviation", "fisheries", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7358 - Textes generaux",
    lawReference: "BO n 7358",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2024/BO_7358_fr.pdf",
    tags: ["official-bulletin", "social-protection", "transport", "administration", "2024"]
  },
  {
    documentTitle: "Bulletin officiel n 7350 - Textes generaux",
    lawReference: "BO n 7350",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2024/BO_7350_fr.pdf",
    tags: ["official-bulletin", "social-protection", "health", "regulated-professions", "2024"]
  },
  {
    documentTitle: "Bulletin officiel n 7470 - Textes generaux",
    lawReference: "BO n 7470",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7470_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7474 - Textes generaux",
    lawReference: "BO n 7474",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7474_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7480 - Textes generaux",
    lawReference: "BO n 7480",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7480_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7492 - Textes generaux",
    lawReference: "BO n 7492",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7492_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7496 - Textes generaux",
    lawReference: "BO n 7496",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7496_fr.pdf",
    tags: ["official-bulletin", "environment", "consumer", "food-safety", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7500 - Textes generaux",
    lawReference: "BO n 7500",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2026/BO_7500_fr.pdf",
    tags: ["official-bulletin", "financial-market", "agriculture", "transport", "2026"]
  },
  {
    documentTitle: "Bulletin officiel n 7366 - Textes generaux",
    lawReference: "BO n 7366",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7366_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7370 - Textes generaux",
    lawReference: "BO n 7370",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7370_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7376 - Textes generaux",
    lawReference: "BO n 7376",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7376_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7380 - Textes generaux",
    lawReference: "BO n 7380",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7380_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7384 - Textes generaux",
    lawReference: "BO n 7384",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7384_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7388 - Textes generaux",
    lawReference: "BO n 7388",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7388_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7392 - Textes generaux",
    lawReference: "BO n 7392",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7392_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7396 - Textes generaux",
    lawReference: "BO n 7396",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7396_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7400 - Textes generaux",
    lawReference: "BO n 7400",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7400_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7404 - Textes generaux",
    lawReference: "BO n 7404",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7404_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7410 - Textes generaux",
    lawReference: "BO n 7410",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7410_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7414 - Textes generaux",
    lawReference: "BO n 7414",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7414_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7418 - Textes generaux",
    lawReference: "BO n 7418",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7418_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7422 - Textes generaux",
    lawReference: "BO n 7422",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7422_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7428 - Textes generaux",
    lawReference: "BO n 7428",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7428_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7432 - Textes generaux",
    lawReference: "BO n 7432",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7432_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7436 - Textes generaux",
    lawReference: "BO n 7436",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7436_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7440 - Textes generaux",
    lawReference: "BO n 7440",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7440_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7444 - Textes generaux",
    lawReference: "BO n 7444",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7444_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7448 - Textes generaux",
    lawReference: "BO n 7448",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7448_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7454 - Textes generaux",
    lawReference: "BO n 7454",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7454_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7458 - Textes generaux",
    lawReference: "BO n 7458",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7458_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7462 - Textes generaux",
    lawReference: "BO n 7462",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2025/BO_7462_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2025"]
  },
  {
    documentTitle: "Bulletin officiel n 7340 - Textes generaux",
    lawReference: "BO n 7340",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2024/BO_7340_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2024"]
  },
  {
    documentTitle: "Bulletin officiel n 7344 - Textes generaux",
    lawReference: "BO n 7344",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2024/BO_7344_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2024"]
  },
  {
    documentTitle: "Bulletin officiel n 7354 - Textes generaux",
    lawReference: "BO n 7354",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2024/BO_7354_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2024"]
  },
  {
    documentTitle: "Bulletin officiel n 7362 - Textes generaux",
    lawReference: "BO n 7362",
    category: "official-bulletin",
    sourceName: "Secretariat General du Gouvernement - Bulletin officiel",
    sourceUrl: "https://www.sgg.gov.ma/BO/FR/2873/2024/BO_7362_fr.pdf",
    tags: ["official-bulletin", "public-law", "administration", "2024"]
  }
];

module.exports = {
  otherLawSources
};
