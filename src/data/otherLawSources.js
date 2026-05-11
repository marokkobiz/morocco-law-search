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
<<<<<<< HEAD
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
=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  }
];

module.exports = {
  otherLawSources
};
