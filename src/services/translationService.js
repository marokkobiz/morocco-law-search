const { translate } = require("@vitalets/google-translate-api");

const translationCache = new Map();
const pendingTranslations = new Map();
const providerCooldowns = new Map();
const MYMEMORY_MAX_BYTES = 450;
const RATE_LIMIT_COOLDOWN_MS = 2 * 60 * 60 * 1000;
const NETWORK_COOLDOWN_MS = 10 * 60 * 1000;
let translationQueue = Promise.resolve();

class TranslationUnavailableError extends Error {
  constructor(message, providerErrors = []) {
    super(message);
    this.name = "TranslationUnavailableError";
    this.providerErrors = providerErrors;
  }
}

const wait = (ms) => new Promise((resolve) => {
  setTimeout(resolve, ms);
});

const enqueueTranslationTask = (task) => {
  const queuedTask = translationQueue
    .catch(() => undefined)
    .then(async () => {
      const result = await task();
      await wait(350);
      return result;
    });

  translationQueue = queuedTask.catch(() => undefined);
  return queuedTask;
};

const getProviderCooldown = (providerName) => providerCooldowns.get(providerName) || 0;

const isProviderAvailable = (providerName) => Date.now() >= getProviderCooldown(providerName);

const pauseProvider = (providerName, durationMs) => {
  providerCooldowns.set(providerName, Date.now() + durationMs);
};

const getErrorCode = (error) => error?.code || error?.errno || error?.cause?.code;

const isNetworkError = (error) => {
  const errorCode = getErrorCode(error);
  const errorMessage = String(error?.message || "").toLowerCase();

  return (
    error?.name === "AbortError" ||
    error?.name === "FetchError" ||
    errorMessage.includes("fetch failed") ||
    errorCode === "ETIMEDOUT" ||
    errorCode === "ECONNRESET" ||
    errorCode === "EAI_AGAIN" ||
    errorCode === "ENOTFOUND"
  );
};

const shouldPauseProvider = (error) => isRateLimitedError(error) || isNetworkError(error);

const getProviderPauseDuration = (error) =>
  isRateLimitedError(error) ? RATE_LIMIT_COOLDOWN_MS : NETWORK_COOLDOWN_MS;

const normalizeForFreeTranslation = (text) =>
  text
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[’‘]/g, "'")
    .replace(/[“”]/g, '"')
    .replace(/[‐‑‒–—]/g, "-");

const fetchWithTimeout = async (url, options = {}, timeoutMs = 15000) => {
  const controller = new AbortController();
  const timeout = setTimeout(() => {
    controller.abort();
  }, timeoutMs);

  try {
    return await fetch(url, {
      ...options,
      signal: controller.signal
    });
  } finally {
    clearTimeout(timeout);
  }
};

const splitLongText = (text, maxLength) => {
  const chunks = [];

  for (let index = 0; index < text.length; index += maxLength) {
    chunks.push(text.slice(index, index + maxLength));
  }

  return chunks;
};

const splitTextByByteLength = (text, maxBytes = MYMEMORY_MAX_BYTES) => {
  const words = text.split(/\s+/).filter(Boolean);
  const chunks = [];
  let currentChunk = "";

  for (const word of words) {
    const candidate = currentChunk ? `${currentChunk} ${word}` : word;

    if (Buffer.byteLength(candidate, "utf8") <= maxBytes) {
      currentChunk = candidate;
      continue;
    }

    if (currentChunk) {
      chunks.push(currentChunk);
    }

    if (Buffer.byteLength(word, "utf8") <= maxBytes) {
      currentChunk = word;
      continue;
    }

    currentChunk = "";
    let partial = "";

    for (const character of word) {
      const candidatePartial = `${partial}${character}`;

      if (Buffer.byteLength(candidatePartial, "utf8") > maxBytes) {
        chunks.push(partial);
        partial = character;
        continue;
      }

      partial = candidatePartial;
    }

    if (partial) {
      currentChunk = partial;
    }
  }

  if (currentChunk) {
    chunks.push(currentChunk);
  }

  return chunks;
};

const splitTextIntoChunks = (text, maxLength = 900) => {
  const normalizedText = text.replace(/\s+/g, " ").trim();

  if (!normalizedText) {
    return [];
  }

  if (normalizedText.length <= maxLength) {
    return [normalizedText];
  }

  const sentences = normalizedText.split(/(?<=[.!?])\s+/);
  const chunks = [];
  let currentChunk = "";

  for (const sentence of sentences) {
    if (!sentence) {
      continue;
    }

    if (sentence.length > maxLength) {
      if (currentChunk) {
        chunks.push(currentChunk);
        currentChunk = "";
      }

      chunks.push(...splitLongText(sentence, maxLength));
      continue;
    }

    if (!currentChunk) {
      currentChunk = sentence;
      continue;
    }

    if ((currentChunk + " " + sentence).length <= maxLength) {
      currentChunk += " " + sentence;
      continue;
    }

    chunks.push(currentChunk);
    currentChunk = sentence;
  }

  if (currentChunk) {
    chunks.push(currentChunk);
  }

  return chunks;
};

const isRateLimitedError = (error) =>
  error?.name === "TooManyRequestsError" || error?.status === 429 || error?.statusCode === 429;

const translateWithGooglePublic = async (chunk, { from = "auto", to = "en" } = {}) => {
  const params = new URLSearchParams({
    client: "gtx",
    sl: from === "auto" ? "auto" : from,
    tl: to,
    dt: "t",
    q: normalizeForFreeTranslation(chunk)
  });
  const response = await fetchWithTimeout(
    `https://translate.googleapis.com/translate_a/single?${params.toString()}`
  );

  if (!response.ok) {
    const error = new Error(`Google public translation failed with status ${response.status}`);
    error.status = response.status;
    throw error;
  }

  const payload = await response.json();
  const translatedText = Array.isArray(payload?.[0])
    ? payload[0].map((segment) => segment?.[0] || "").join("")
    : "";

  if (!translatedText) {
    throw new Error("Google public translation did not return translated text");
  }

  return translatedText;
};

const translateChunkWithRetry = async (chunk, options, retries = 2) => {
  let lastError;

  for (let attempt = 0; attempt <= retries; attempt += 1) {
    try {
      const result = await translate(chunk, options);
      return result.text;
    } catch (error) {
      lastError = error;

      if (isRateLimitedError(error)) {
        throw error;
      }

      if (attempt < retries) {
        await wait(500 * (attempt + 1));
      }
    }
  }

  throw lastError;
};

const translateWithMyMemory = async (chunk, { from = "fr", to = "en" } = {}) => {
  const sourceLanguage = from === "auto" ? "fr" : from;
  const params = new URLSearchParams({
    q: chunk,
    langpair: `${sourceLanguage}|${to}`,
    mt: "1"
  });

  const response = await fetchWithTimeout(`https://api.mymemory.translated.net/get?${params.toString()}`);

  if (!response.ok) {
    const error = new Error(`MyMemory translation failed with status ${response.status}`);
    error.status = response.status;
    throw error;
  }

  const payload = await response.json();
  const translatedText = payload?.responseData?.translatedText;

  if (!translatedText) {
    throw new Error("MyMemory did not return translated text");
  }

  return translatedText;
};

const translateWithMyMemoryFallback = async (chunk, options) => {
  const chunks = splitTextByByteLength(chunk);
  const translatedChunks = [];

  for (const smallerChunk of chunks) {
    translatedChunks.push(await translateWithMyMemory(smallerChunk, options));
    await wait(200);
  }

  return translatedChunks.join(" ");
};

const translateChunkSafely = async (chunk, options) => {
  const errors = [];

  if (isProviderAvailable("google-public")) {
    try {
      return await translateWithGooglePublic(chunk, options);
    } catch (error) {
      errors.push(`google-public: ${error.message}`);

      if (shouldPauseProvider(error)) {
        pauseProvider("google-public", getProviderPauseDuration(error));
      }
    }
  } else {
    errors.push("google-public: cooling down after recent failure");
  }

  if (isProviderAvailable("google-package")) {
    try {
      return await translateChunkWithRetry(chunk, options);
    } catch (error) {
      errors.push(`google-package: ${error.message}`);

      if (shouldPauseProvider(error)) {
        pauseProvider("google-package", getProviderPauseDuration(error));
      }
    }
  } else {
    errors.push("google-package: cooling down after recent failure");
  }

  if (isProviderAvailable("mymemory")) {
    try {
      return await translateWithMyMemoryFallback(chunk, options);
    } catch (error) {
      errors.push(`mymemory: ${error.message}`);

      if (shouldPauseProvider(error)) {
        pauseProvider("mymemory", getProviderPauseDuration(error));
      }
    }
  } else {
    errors.push("mymemory: cooling down after recent failure");
  }

  throw new TranslationUnavailableError(
    `Inline translation is temporarily unavailable. ${errors.join(" | ")}`,
    errors
  );
};

const translateText = async (text, { from = "auto", to = "en" } = {}) => {
  const chunks = splitTextIntoChunks(text);

  if (chunks.length === 0) {
    return "";
  }

  const translatedChunks = [];

  for (const chunk of chunks) {
    translatedChunks.push(await translateChunkSafely(chunk, { from, to }));
    await wait(150);
  }

  return translatedChunks.join(" ");
};

const buildExternalTranslationUrl = (law, targetLanguage = "en") => {
  return `https://translate.google.com/?sl=${encodeURIComponent(
    law.language || "auto"
  )}&tl=${encodeURIComponent(targetLanguage)}&text=${encodeURIComponent(
    `${law.title}\n\n${law.content}`.slice(0, 4200)
  )}&op=translate`;
};

const isTranslationUnavailableError = (error) =>
  error?.name === "TranslationUnavailableError";

const translateLaw = async (law, targetLanguage = "en") => {
  const sourceLanguage = law.language || "auto";
  const cacheKey = `${law.id}:${sourceLanguage}:${targetLanguage}:${law.content.length}`;

  if (translationCache.has(cacheKey)) {
    return translationCache.get(cacheKey);
  }

  if (pendingTranslations.has(cacheKey)) {
    return pendingTranslations.get(cacheKey);
  }

  const pendingTranslation = enqueueTranslationTask(async () => {
    const translatedTitle = await translateText(law.title, {
      from: sourceLanguage,
      to: targetLanguage
    });
    const translatedContent = await translateText(law.content, {
      from: sourceLanguage,
      to: targetLanguage
    });

    const payload = {
      id: law.id,
      sourceLanguage,
      targetLanguage,
      translatedTitle,
      translatedContent
    };

    translationCache.set(cacheKey, payload);
    pendingTranslations.delete(cacheKey);
    return payload;
  });

  pendingTranslations.set(cacheKey, pendingTranslation);

  try {
    return await pendingTranslation;
  } catch (error) {
    pendingTranslations.delete(cacheKey);
    throw error;
  }
};

module.exports = {
  translateLaw,
  buildExternalTranslationUrl,
  isTranslationUnavailableError
};
