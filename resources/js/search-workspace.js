import './bootstrap';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const api = async (url, opts = {}) => {
  const res = await fetch(url, { headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', ...opts.headers }, ...opts });
  if (!res.ok) throw new Error(res.statusText);
  return res.json();
};

const el = (id) => document.getElementById(id);

const overviewStats = async () => {
  try {
    const data = await api('/api/laws/overview');
    el('stat-articles').textContent = (data.totalArticles ?? 0).toLocaleString();
    el('stat-sources').textContent = (data.totalSources ?? 0).toLocaleString();
    el('stat-areas').textContent = (data.totalCategories ?? 0).toLocaleString();
    const list = el('category-list');
    list.innerHTML = '';
    (data.categories ?? []).forEach((cat) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'w-full flex items-center justify-between px-3 py-2 rounded-xl text-sm transition-all hover:bg-blue-50 hover:text-blue-700 cursor-pointer group';
      const label = (cat.category || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
      btn.innerHTML = `<span class="font-medium text-gray-600 group-hover:text-blue-700">${label}</span><span class="text-xs font-semibold text-gray-400 group-hover:text-blue-500">${(cat.articleCount ?? 0).toLocaleString()}</span>`;
      btn.addEventListener('click', () => doSearch(cat.category));
      list.appendChild(btn);
    });
  } catch {}
};

const showState = (state) => {
  const isResults = state === 'results';
  ['initial', 'loading', 'empty'].forEach((s) => el(`results-${s}`)?.classList.toggle('hidden', s !== state));
  el('results-header')?.classList.toggle('hidden', state === 'initial' || state === 'loading');
  el('results-list').classList.toggle('hidden', !isResults);
};

const renderResults = (data) => {
  el('results-title').textContent = `${data.query}`;
  el('result-count').textContent = `${data.count} result${data.count !== 1 ? 's' : ''}`;
  const warn = el('translation-warning');
  if (data.translationWarning) { warn.textContent = data.translationWarning; warn.classList.remove('hidden'); }
  else warn.classList.add('hidden');
  showState(data.count > 0 ? 'header' : 'empty');
  if (data.count === 0) return;
  const list = el('results-list');
  list.innerHTML = '';
  data.results.forEach((r) => list.appendChild(buildResultCard(r)));
  showState('results');
};

const buildResultCard = (r) => {
  const card = document.createElement('article');
  card.className = 'card p-6 hover:shadow-md transition-shadow';
  card.dataset.resultId = r.id;

  const topRow = document.createElement('div');
  topRow.className = 'flex items-center gap-2 mb-3 flex-wrap';
  if (r.category) {
    const chip = document.createElement('span');
    chip.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100';
    chip.textContent = r.category;
    topRow.appendChild(chip);
  }
  if (r.article_number) {
    const art = document.createElement('span');
    art.className = 'text-xs text-gray-400 font-medium';
    art.textContent = r.article_number;
    topRow.appendChild(art);
  }
  card.appendChild(topRow);

  const title = document.createElement('h3');
  title.className = 'text-base font-bold text-gray-900 leading-snug';
  title.textContent = r.title || r.document_title || '';
  card.appendChild(title);

  const preview = document.createElement('p');
  preview.className = 'mt-2.5 text-sm text-gray-600 leading-relaxed line-clamp-3';
  preview.textContent = r.content || '';
  card.appendChild(preview);

  if (r.tags && r.tags.length) {
    const tagWrap = document.createElement('div');
    tagWrap.className = 'flex flex-wrap gap-1.5 mt-3';
    r.tags.forEach((t) => {
      const span = document.createElement('span');
      span.className = 'px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-500';
      span.textContent = t;
      tagWrap.appendChild(span);
    });
    card.appendChild(tagWrap);
  }

  const srcRow = document.createElement('div');
  srcRow.className = 'flex items-center justify-between mt-4 pt-4 border-t border-gray-100';
  const srcLeft = document.createElement('div');
  srcLeft.className = 'flex items-center gap-2 text-xs';
  srcLeft.innerHTML = `<span class="font-medium text-gray-400">Source:</span><span class="font-semibold text-gray-700">${r.source_name || ''}</span>`;
  srcRow.appendChild(srcLeft);
  if (r.source_url) {
    const a = document.createElement('a');
    a.href = r.source_url;
    a.target = '_blank';
    a.className = 'text-xs font-semibold text-blue-600 hover:text-blue-700 no-underline';
    a.textContent = `Open source \u2192`;
    srcRow.appendChild(a);
  }
  card.appendChild(srcRow);

  const actRow = document.createElement('div');
  actRow.className = 'flex items-center gap-2 mt-3 pt-3 border-t border-gray-50';
  ['en', 'ar'].forEach((lang) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'translate-btn text-xs font-semibold text-blue-600 hover:text-white hover:bg-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg transition-colors cursor-pointer';
    btn.textContent = lang === 'en' ? 'Translate EN' : 'Translate AR';
    btn.dataset.id = r.id;
    btn.dataset.target = lang;
    btn.addEventListener('click', () => translateArticle(r.id, lang, card));
    actRow.appendChild(btn);
  });
  card.appendChild(actRow);

  const txBox = document.createElement('div');
  txBox.className = 'hidden mt-3 p-4 rounded-xl bg-amber-50 border border-amber-200';
  txBox.id = `translation-${r.id}`;
  card.appendChild(txBox);

  return card;
};

const translateArticle = async (id, target, card) => {
  const box = card.querySelector(`#translation-${id}`);
  const btns = card.querySelectorAll('.translate-btn');
  btns.forEach((b) => { b.disabled = true; b.textContent = 'Translating...'; b.classList.add('opacity-50', 'cursor-not-allowed'); });
  try {
    const data = await api(`/api/laws/${id}/translate?target=${target}`);
    box.innerHTML = '';
    if (data.translatedTitle) {
      const t = document.createElement('p');
      t.className = 'text-sm font-bold text-gray-900 mb-1';
      t.textContent = data.translatedTitle;
      box.appendChild(t);
    }
    if (data.translatedContent) {
      const c = document.createElement('p');
      c.className = 'text-sm text-gray-700 leading-relaxed';
      c.textContent = data.translatedContent;
      box.appendChild(c);
    }
    box.classList.remove('hidden');
  } catch {
    box.innerHTML = `<p class="text-sm text-red-600">Translation unavailable.</p>`;
    box.classList.remove('hidden');
  }
  btns.forEach((b) => { b.disabled = false; const l = b.dataset.target; b.textContent = l === 'en' ? 'Translate (EN)' : 'Translate (AR)'; b.classList.remove('opacity-50', 'cursor-not-allowed'); });
};

const doSearch = async (query, mode = 'smart') => {
  if (!query || !query.trim()) return;
  el('search-input').value = query;
  el('suggestions-panel').classList.add('hidden');
  showState('loading');
  try {
    const data = await api(`/api/laws/search?q=${encodeURIComponent(query)}&translation_mode=${mode}`);
    renderResults(data);
  } catch {
    showState('empty');
  }
};

// Suggestions
let suggestTimer;
el('search-input').addEventListener('input', () => {
  clearTimeout(suggestTimer);
  const q = el('search-input').value.trim();
  if (q.length < 2) { el('suggestions-panel').classList.add('hidden'); return; }
  suggestTimer = setTimeout(async () => {
    try {
      const data = await api(`/api/laws/suggestions?q=${encodeURIComponent(q)}`);
      const panel = el('suggestions-panel');
      panel.innerHTML = '';
      if (!data.suggestions || !data.suggestions.length) { panel.classList.add('hidden'); return; }
      data.suggestions.forEach((s) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'w-full flex items-center gap-3 px-4 py-3 hover:bg-blue-50 transition-colors text-left cursor-pointer border-b border-gray-50 last:border-b-0';
        const label = s.label || s.text || s;
        const type = s.type || '';
        btn.innerHTML = `<span class="text-sm text-gray-900 flex-1">${label}</span>${type ? `<span class="px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-600">${type}</span>` : ''}`;
        btn.addEventListener('click', () => doSearch(label));
        panel.appendChild(btn);
      });
      panel.classList.remove('hidden');
    } catch { el('suggestions-panel').classList.add('hidden'); }
  }, 300);
});

el('search-input').addEventListener('blur', () => setTimeout(() => el('suggestions-panel').classList.add('hidden'), 200));
el('search-input').addEventListener('focus', () => { if (el('suggestions-panel').children.length) el('suggestions-panel').classList.remove('hidden'); });

el('search-form').addEventListener('submit', (e) => { e.preventDefault(); doSearch(el('search-input').value); });

document.querySelectorAll('.quick-btn').forEach((btn) => btn.addEventListener('click', () => doSearch(btn.dataset.quick)));

// Search on Enter
el('search-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); el('suggestions-panel').classList.add('hidden'); doSearch(el('search-input').value); } });

el('chat-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const input = el('chat-input');
  const q = input.value.trim();
  if (!q) return;
  input.value = '';
  const feed = el('chat-feed');
  const userBubble = document.createElement('div');
  userBubble.className = 'flex items-start gap-2 justify-end';
  userBubble.innerHTML = `<div class="bg-blue-600 text-white rounded-xl rounded-tr-none px-3 py-2 max-w-[85%]"><p class="text-xs leading-relaxed">${q}</p></div>`;
  feed.appendChild(userBubble);
  const loadingBubble = document.createElement('div');
  loadingBubble.className = 'flex items-start gap-2';
  loadingBubble.id = 'chat-loading';
  loadingBubble.innerHTML = `<div class="w-6 h-6 rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shrink-0"><svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></div><div class="bg-white rounded-xl rounded-tl-none px-3 py-2 border border-gray-100"><div class="flex items-center gap-1.5"><div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-bounce" style="animation-delay:0ms"></div><div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-bounce" style="animation-delay:150ms"></div><div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-bounce" style="animation-delay:300ms"></div></div></div>`;
  feed.appendChild(loadingBubble);
  feed.scrollTop = feed.scrollHeight;
  try {
    const data = await api(`/api/laws/search?q=${encodeURIComponent(q)}&translation_mode=smart`);
    el('chat-loading').remove();
    const aiBubble = document.createElement('div');
    aiBubble.className = 'flex items-start gap-2';
    let html = `<div class="w-6 h-6 rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shrink-0"><svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></div>`;
    html += `<div class="bg-white rounded-xl rounded-tl-none px-3 py-2 border border-gray-100 flex-1">`;
    if (data.count > 0) {
      html += `<p class="text-xs text-gray-700 leading-relaxed mb-2">Found ${data.count} result${data.count !== 1 ? 's' : ''}:</p><ol class="list-decimal list-inside space-y-0.5">`;
      data.results.slice(0, 5).forEach((r) => {
        html += `<li class="text-xs text-gray-600"><span class="font-medium">${r.title || r.document_title || ''}</span>${r.article_number ? ` — ${r.article_number}` : ''}</li>`;
      });
      html += `</ol>`;
    } else {
      html += `<p class="text-xs text-gray-600 leading-relaxed">No results found. Try rephrasing.</p>`;
    }
    html += `<button type="button" class="mt-2 text-xs font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded-lg transition-colors cursor-pointer" onclick="doSearch('${q.replace(/'/g, "\\'")}')">View all &rarr;</button>`;
    html += `</div>`;
    aiBubble.innerHTML = html;
    feed.appendChild(aiBubble);
    feed.scrollTop = feed.scrollHeight;
  } catch {
    const loading = el('chat-loading');
    if (loading) loading.remove();
    const errBubble = document.createElement('div');
    errBubble.className = 'flex items-start gap-2';
    errBubble.innerHTML = `<div class="w-6 h-6 rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shrink-0"><svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div class="bg-red-50 rounded-xl rounded-tl-none px-3 py-2 border border-red-100"><p class="text-xs text-red-600">Something went wrong.</p></div>`;
    feed.appendChild(errBubble);
    feed.scrollTop = feed.scrollHeight;
  }
});

// Assistant popup toggle
const openAssistant = () => {
  el('assistant-popup').classList.remove('hidden');
  el('assistant-fab').classList.add('hidden');
  el('chat-input').focus();
};
const closeAssistant = () => {
  el('assistant-popup').classList.add('hidden');
  el('assistant-fab').classList.remove('hidden');
};
el('assistant-toggle')?.addEventListener('click', openAssistant);
el('assistant-fab')?.addEventListener('click', openAssistant);
el('assistant-close')?.addEventListener('click', closeAssistant);

// Sidebar toggle (mobile)
const sidebar = el('sidebar-panel');
const overlay = el('sidebar-overlay');
el('sidebar-toggle')?.addEventListener('click', () => {
  sidebar?.classList.toggle('hidden');
  overlay?.classList.toggle('hidden');
});
overlay?.addEventListener('click', () => {
  sidebar?.classList.add('hidden');
  overlay?.classList.add('hidden');
});

// Reset workspace
const resetWorkspace = () => {
  el('search-input').value = '';
  el('clear-search')?.classList.add('hidden');
  el('suggestions-panel')?.classList.add('hidden');
  ['initial', 'loading', 'empty', 'header', 'results'].forEach((s) => el(`results-${s}`)?.classList.add('hidden'));
  el('results-list')?.classList.add('hidden');
  el('results-initial')?.classList.remove('hidden');
  el('translation-warning')?.classList.add('hidden');
};

// Clear search
const clearBtn = el('clear-search');
const searchInput = el('search-input');
searchInput?.addEventListener('input', () => {
  clearBtn?.classList.toggle('hidden', !searchInput.value.trim());
});
clearBtn?.addEventListener('click', () => {
  resetWorkspace();
  searchInput.focus();
});

el('clear-header-search')?.addEventListener('click', resetWorkspace);

overviewStats();
