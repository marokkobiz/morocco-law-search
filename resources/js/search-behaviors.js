(function () {
  var originalFetch = window.fetch.bind(window);

  window.fetch = function (input, init) {
    if (init === void 0) init = {};
    var url = typeof input === 'string' ? input : (input == null ? void 0 : input.url) || '';
    var method = String(init.method || (input == null ? void 0 : input.method) || 'GET').toUpperCase();

    if (url.startsWith('/api/') && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
      var headers = new Headers(init.headers || (input == null ? void 0 : input.headers) || {});
      var csrfToken = document.querySelector('meta[name="csrf-token"]') == null ? void 0 : document.querySelector('meta[name="csrf-token"]').content;

      if (csrfToken) {
        headers.set('X-CSRF-TOKEN', csrfToken);
      }

      headers.set('X-Requested-With', 'XMLHttpRequest');
      init = Object.assign({}, init, { headers: headers });
    }

    return originalFetch(input, init);
  };
})();

(function () {
  document.addEventListener('click', function (event) {
    var landingButton = event.target.closest('.topbar-link');

    if (landingButton && landingButton.textContent.trim() === 'Landing') {
      event.preventDefault();
      event.stopImmediatePropagation();
      window.location.assign('/');
    }
  }, true);
})();

(function () {
  var pendingKey = 'marokko.pendingArticleJump';

  var normalize = function (value) {
    return (value || '')
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^\p{L}\p{N}]+/gu, ' ')
      .trim();
  };

  var parseArticleSuggestion = function (text) {
    var value = (text || '').toString().trim();
    var match = value.match(/^(.*?)\s+-\s+Article\s+(.+)$/i);

    if (!match) {
      return null;
    }

    return {
      document: normalize(match[1]),
      article: normalize('Article ' + match[2]),
      raw: value,
    };
  };

  var rememberArticleJump = function (row) {
    var label = row.querySelector('span');
    var type = row.querySelector('strong');
    var text = (label == null ? void 0 : label.dataset.originalLabel) || (label == null ? void 0 : label.textContent.trim()) || '';

    if (type && type.textContent.trim().toUpperCase() !== 'ARTICLE') {
      return;
    }

    var parsed = parseArticleSuggestion(text);

    if (!parsed) {
      return;
    }

    sessionStorage.setItem(pendingKey, JSON.stringify(Object.assign({}, parsed, { createdAt: Date.now() })));
  };

  var findJumpTarget = function (pending) {
    var cards = Array.from(document.querySelectorAll('.result-card'));

    var found = cards.find(function (card) {
      var title = normalize(card.querySelector('h3').textContent || '');
      var article = normalize(card.querySelector('.article-number').textContent || '');
      var source = normalize(card.querySelector('.source-box strong').textContent || '');

      return article === pending.article && (title.includes(pending.document) || source.includes(pending.document) || pending.document.includes(title));
    });

    if (found) {
      return found;
    }

    return cards.find(function (card) {
      var title = normalize(card.querySelector('h3').textContent || '');
      return normalize(pending.raw) === title || title.includes(normalize(pending.raw));
    });
  };

  var tryJumpToPendingArticle = function () {
    var raw = sessionStorage.getItem(pendingKey);

    if (!raw) {
      return;
    }

    var pending;

    try {
      pending = JSON.parse(raw);
    } catch (_) {
      sessionStorage.removeItem(pendingKey);
      return;
    }

    if (!(pending != null && pending.createdAt) || Date.now() - pending.createdAt > 15000) {
      sessionStorage.removeItem(pendingKey);
      return;
    }

    var target = findJumpTarget(pending);

    if (!target) {
      return;
    }

    sessionStorage.removeItem(pendingKey);
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.classList.add('is-jump-target');
    window.setTimeout(function () { return target.classList.remove('is-jump-target'); }, 2400);
  };

  document.addEventListener('click', function (event) {
    var suggestion = event.target.closest('.suggestion-row');

    if (suggestion) {
      rememberArticleJump(suggestion);
    }
  }, true);

  window.addEventListener('load', tryJumpToPendingArticle);
  new MutationObserver(tryJumpToPendingArticle).observe(document.getElementById('root'), {
    childList: true,
    subtree: true,
  });
})();

(function () {
  var shouldOpenWorkspace = window.location.pathname.replace(/\/+$/, '') === '/app';

  if (!shouldOpenWorkspace) {
    return;
  }

  var openWorkspace = function () {
    var button = document.querySelector('.landing-nav-actions .primary-action, .landing-actions .primary-action, .preview-search button');

    if (button) {
      button.click();
      return true;
    }

    return false;
  };

  var attempts = 0;
  var interval = window.setInterval(function () {
    attempts += 1;

    if (openWorkspace() || attempts > 40) {
      window.clearInterval(interval);
    }
  }, 125);

  window.addEventListener('load', openWorkspace);
  new MutationObserver(openWorkspace).observe(document.getElementById('root'), {
    childList: true,
    subtree: true,
  });
})();
