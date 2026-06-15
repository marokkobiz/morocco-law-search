# RAG Legal Assistant — How It Works

This document explains the Retrieval-Augmented Generation (RAG) system that powers the Legal Assistant chatbot. Read this to understand and explain each piece of the pipeline.

---

## What is RAG?

RAG = **Retrieval-Augmented Generation**. Instead of asking an AI to answer from its training data (which might be wrong or outdated), we first **retrieve** relevant legal documents from our database, then **feed them** to the AI as context so it answers with actual sources.

> Think of it as: give the AI an open-book exam instead of a closed-book one.

---

## The Pipeline

```
User asks a question
        │
        ▼
  1. EMBED the question
     (convert words into a mathematical vector)
        │
        ▼
  2. RETRIEVE similar legal chunks
     (find chunks with the closest vectors)
        │
        ▼
  3. BUILD context prompt
     (package chunks + question for the LLM)
        │
        ▼
  4. GENERATE answer via LLM
     (Ollama reads context, writes answer)
        │
        ▼
  5. RETURN answer + citations
     (user sees AI response with source links)
```

---

## Step-by-step

### 1. Embed the question

The user types "What are the notice periods for termination?"

This text is sent to Ollama's embedding model (`nomic-embed-text`) which returns a **vector** — a list of ~768 numbers that mathematically represents the meaning of the question.

### 2. Retrieve similar legal chunks

We search the `legal_chunks` table (which has all Moroccan law split into small passages) and:

1. Load every chunk that has an embedding (vector) already stored
2. Compare each chunk's vector to the question's vector using **cosine similarity**
3. Keep only chunks with a similarity score above 0.55
4. Take the top 5 most similar chunks

### 3. Build context prompt

The 5 chunks are formatted into a system message like:

```
Here are relevant legal excerpts to help answer the question:

[0] Article 43 — The notice period for termination is...
[1] Article 44 — In case of wrongful dismissal...
```

The user's question is appended as the user message.

### 4. Generate answer via LLM

This prompt is sent to Ollama's chat model (`qwen3:8b`) via `/api/chat`. The model reads the context and writes an answer that cites the provided sources.

### 5. Return answer + citations

The response includes:

- **answer**: the AI's written response
- **citations**: each chunk's content, document title, article number, and source URL

The frontend displays the answer with collapsible source references.

---

## Key Files

| File | Role |
|------|------|
| `app/Services/Ai/LegalRagService.php` | Orchestrates the RAG pipeline (embed → retrieve → generate) |
| `app/Services/Ai/Providers/OllamaChatProvider.php` | Sends prompts to Ollama's `/api/chat` |
| `app/Services/Ai/Providers/OllamaEmbeddingProvider.php` | Sends text to Ollama's `/api/embed` for vector conversion |
| `app/Services/Ai/AiProviderFactory.php` | Returns the right provider based on `AI_PROVIDER` env var |
| `app/Services/LegalEmbeddingService.php` | Utility methods (cosine similarity, vector decoding, checksums) |
| `app/Contracts/Ai/ChatProvider.php` | Interface for any chat provider (Ollama, OpenAI, etc.) |
| `app/Contracts/Ai/EmbeddingProvider.php` | Interface for any embedding provider |
| `app/Http/Controllers/Api/ChatController.php` | HTTP endpoint `POST /api/laws/ask` |
| `resources/js/search-workspace.js` | Frontend — sends user question, renders AI answer |

---

## How to switch AI provider

Change one line in `.env`:

```env
AI_PROVIDER=openai
```

Then create two classes implementing `ChatProvider` and `EmbeddingProvider`, add their config under `providers` in `config/ai.php`, and add a case in `AiProviderFactory`.

No other code changes needed.

---

## Data flow diagram (simplified)

```
Browser                          Laravel                          Ollama
  │                                │                                │
  │  POST /api/laws/ask            │                                │
  │  { question: "..." }           │                                │
  │ ─────────────────────────────> │                                │
  │                                │                                │
  │                                │  POST /api/embed               │
  │                                │  { model, input }              │
  │                                │ ─────────────────────────────> │
  │                                │  ←── [0.12, -0.45, ...] ───── │
  │                                │                                │
  │                                │  SELECT FROM legal_chunks      │
  │                                │  WHERE ... (cosine similarity) │
  │                                │  ──── retrieve top 5 ─────     │
  │                                │                                │
  │                                │  POST /api/chat                │
  │                                │  { model, messages }           │
  │                                │ ─────────────────────────────> │
  │                                │  ←── "According to Article..." │
  │                                │                                │
  │  { answer: "...",             │                                │
  │    citations: [...] }         │                                │
  │ <───────────────────────────── │                                │
  │                                │                                │
```

---

## Why this approach

- **Accurate**: The AI answers only from your legal corpus, not internet rumors
- **Traceable**: Every answer includes source citations users can verify
- **Swappable**: Change the AI provider in `.env`, no code changes
- **Scalable**: New laws are added to the corpus, and the search automatically finds them
