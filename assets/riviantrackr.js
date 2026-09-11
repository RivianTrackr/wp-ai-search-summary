(function() {
  // Session cache helpers
  var CACHE_PREFIX = 'riviantrackr_';
  var CACHE_VERSION_KEY = 'riviantrackr_version';
  var CACHE_TTL = 30 * 60 * 1000; // 30 minutes in milliseconds
  var SOURCES_STATE_KEY = 'riviantrackr_sources_expanded';

  function checkCacheVersion() {
    // Invalidate browser cache if server cache version changed (e.g., model changed)
    try {
      var serverVersion = window.RivianTrackrAI && window.RivianTrackrAI.cacheVersion;
      if (!serverVersion) return;

      var storedVersion = sessionStorage.getItem(CACHE_VERSION_KEY);
      if (storedVersion && storedVersion !== String(serverVersion)) {
        // Version changed - clear all our cached data
        var keysToRemove = [];
        for (var i = 0; i < sessionStorage.length; i++) {
          var key = sessionStorage.key(i);
          if (key && key.indexOf(CACHE_PREFIX) === 0) {
            keysToRemove.push(key);
          }
        }
        keysToRemove.forEach(function(key) {
          sessionStorage.removeItem(key);
        });
      }
      sessionStorage.setItem(CACHE_VERSION_KEY, String(serverVersion));
    } catch (e) {
      // Fail silently
    }
  }

  function getCacheKey(query) {
    // encodeURIComponent is injective, so two different queries can never
    // share a key (the old base64-and-strip approach could collide).
    return CACHE_PREFIX + 'q_' + encodeURIComponent(query);
  }

  function getFromCache(query) {
    try {
      var key = getCacheKey(query);
      var cached = sessionStorage.getItem(key);
      if (!cached) return null;

      var data = JSON.parse(cached);
      if (Date.now() > data.expires) {
        sessionStorage.removeItem(key);
        return null;
      }
      return data.response;
    } catch (e) {
      return null;
    }
  }

  function saveToCache(query, response) {
    try {
      var key = getCacheKey(query);
      var data = {
        response: response,
        expires: Date.now() + CACHE_TTL
      };
      sessionStorage.setItem(key, JSON.stringify(data));
    } catch (e) {
      // Storage full or unavailable - fail silently
    }
  }

  function logSessionCacheHit(query, resultsCount) {
    // Fire and forget - log session cache hit to analytics
    if (!window.RivianTrackrAI || !window.RivianTrackrAI.endpoint) return;
    var logEndpoint = window.RivianTrackrAI.logEndpoint || window.RivianTrackrAI.endpoint.replace('/summary', '/log-session-hit');
    var logHeaders = { 'Content-Type': 'application/x-www-form-urlencoded' };
    if (window.RivianTrackrAI.nonce) {
      logHeaders['X-WP-Nonce'] = window.RivianTrackrAI.nonce;
    }
    try {
      fetch(logEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: logHeaders,
        body: 'q=' + encodeURIComponent(query) + '&results_count=' + (resultsCount || 0)
      }).catch(function() {
        // Analytics logging is not critical
      });
    } catch (e) {
      // Fail silently - analytics logging is not critical
    }
  }

  function showSkeleton(container) {
    container.classList.add('riviantrackr-loading');
    container.setAttribute('aria-busy', 'true');
    container.innerHTML =
      '<div class="riviantrackr-skeleton" aria-hidden="true">' +
        '<div class="riviantrackr-skeleton-line riviantrackr-skeleton-line-full"></div>' +
        '<div class="riviantrackr-skeleton-line riviantrackr-skeleton-line-full"></div>' +
        '<div class="riviantrackr-skeleton-line riviantrackr-skeleton-line-medium"></div>' +
        '<div class="riviantrackr-skeleton-line riviantrackr-skeleton-line-short"></div>' +
      '</div>';
  }

  function markLoaded(container) {
    container.classList.remove('riviantrackr-loading');
    container.classList.add('riviantrackr-loaded');
    container.setAttribute('aria-busy', 'false');
  }

  // The container is an aria-live="polite" region, so a plain paragraph is
  // announced once; role="alert" here would make screen readers announce twice.
  function renderError(container, message) {
    markLoaded(container);
    var errorP = document.createElement('p');
    errorP.className = 'riviantrackr-error';
    errorP.textContent = String(message);
    container.innerHTML = '';
    container.appendChild(errorP);
  }

  function renderAnswer(container, html) {
    markLoaded(container);
    container.innerHTML = html;
    showFeedback();
    restoreSourcesState(container);
  }

  function showFeedback() {
    var feedback = document.getElementById('riviantrackr-feedback');
    if (feedback) {
      feedback.hidden = false;
    }
  }

  // Re-open the sources list if the visitor left it open on a previous search.
  function restoreSourcesState(container) {
    try {
      if (localStorage.getItem(SOURCES_STATE_KEY) !== '1') return;
      var btn = container.querySelector('.riviantrackr-sources-toggle');
      if (!btn) return;
      var wrapper = btn.closest('.riviantrackr-sources');
      var list = wrapper && wrapper.querySelector('.riviantrackr-sources-list');
      if (list && list.hasAttribute('hidden')) {
        list.removeAttribute('hidden');
        btn.textContent = btn.getAttribute('data-label-hide') || 'Hide sources';
        btn.setAttribute('aria-expanded', 'true');
      }
    } catch (e) {}
  }

  // Delegated handlers: registered once, before any content is rendered, so
  // they work for cached and freshly fetched summaries alike.
  function bindHandlers() {
    // Sources toggle (persists expanded state in localStorage)
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.riviantrackr-sources-toggle');
      if (!btn) return;

      var wrapper = btn.closest('.riviantrackr-sources');
      if (!wrapper) return;

      var list = wrapper.querySelector('.riviantrackr-sources-list');
      if (!list) return;

      var isHidden = list.hasAttribute('hidden');
      var showLabel = btn.getAttribute('data-label-show') || 'Show sources';
      var hideLabel = btn.getAttribute('data-label-hide') || 'Hide sources';

      if (isHidden) {
        list.removeAttribute('hidden');
        btn.textContent = hideLabel;
        btn.setAttribute('aria-expanded', 'true');
        try { localStorage.setItem(SOURCES_STATE_KEY, '1'); } catch (err) {}
      } else {
        list.setAttribute('hidden', 'hidden');
        btn.textContent = showLabel;
        btn.setAttribute('aria-expanded', 'false');
        try { localStorage.removeItem(SOURCES_STATE_KEY); } catch (err) {}
      }
    });

    // Feedback buttons
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.riviantrackr-feedback-btn');
      if (!btn) return;

      var feedbackContainer = document.getElementById('riviantrackr-feedback');
      if (!feedbackContainer) return;

      var helpful = btn.getAttribute('data-helpful') === '1';
      var q = (window.RivianTrackrAI.query || '').trim();
      var feedbackEndpoint = window.RivianTrackrAI.feedbackEndpoint;

      if (!q || !feedbackEndpoint) return;

      var buttons = feedbackContainer.querySelectorAll('.riviantrackr-feedback-btn');
      var prompt = feedbackContainer.querySelector('.riviantrackr-feedback-prompt');
      var thanks = feedbackContainer.querySelector('.riviantrackr-feedback-thanks');

      function setButtons(disabled) {
        buttons.forEach(function(b) { b.disabled = disabled; });
      }

      function showMessage(message) {
        if (!thanks) return;
        thanks.hidden = false;
        if (message) thanks.textContent = message;
      }

      // Disable buttons immediately
      setButtons(true);

      fetch(feedbackEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.RivianTrackrAI.nonce || ''
        },
        body: JSON.stringify({ q: q, helpful: helpful ? 1 : 0 })
      })
      .then(function(response) {
        return response.json().then(function(data) {
          return { ok: response.ok, data: data || {} };
        }, function() {
          return { ok: response.ok, data: {} };
        });
      })
      .then(function(result) {
        var data = result.data;
        if (result.ok && data.success) {
          if (prompt) prompt.hidden = true;
          showMessage(data.message);
          return;
        }
        // Duplicate vote: the vote is already counted, so keep the buttons off.
        if (result.ok && data.success === false && /already/i.test(String(data.message || ''))) {
          if (prompt) prompt.hidden = true;
          showMessage(data.message);
          return;
        }
        // Real failure (stale nonce, rate limit, server error): let them retry.
        setButtons(false);
        showMessage(data.message || 'Could not record your feedback. Please try again.');
      })
      .catch(function() {
        setButtons(false);
        showMessage('Could not record your feedback. Please try again.');
      });
    });
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function() {
    if (!window.RivianTrackrAI) return;

    bindHandlers();

    // Check if server cache was cleared (model changed, etc.) and invalidate browser cache
    checkCacheVersion();

    var container = document.getElementById('riviantrackr-search-summary-content');
    if (!container) return;

    var q = (window.RivianTrackrAI.query || '').trim();
    if (!q) return;

    // Show skeleton loading immediately
    showSkeleton(container);

    // Check session cache first
    var cached = getFromCache(q);
    if (cached) {
      if (cached.answer_html) {
        renderAnswer(container, cached.answer_html);
        // Only a real summary counts as a session cache hit. Cached
        // "no results" / off-topic responses must not be logged as
        // successful searches.
        logSessionCacheHit(q, cached.results_count);
      } else if (cached.error) {
        renderError(container, cached.error);
      } else {
        markLoaded(container);
      }
      return;
    }

    var endpoint = window.RivianTrackrAI.endpoint + '?q=' + encodeURIComponent(q);

    // Append JS challenge token for bot detection hardening
    if (window.RivianTrackrAI.botToken && window.RivianTrackrAI.botTokenTs) {
      endpoint += '&bt=' + encodeURIComponent(window.RivianTrackrAI.botToken) + '&bts=' + encodeURIComponent(window.RivianTrackrAI.botTokenTs);
    }

    // Append honeypot field value (should always be empty for real users)
    var hpField = document.getElementById('riviantrackr-hp');
    endpoint += '&hp=' + encodeURIComponent(hpField ? hpField.value : '');

    // Set timeout with AbortController to actually cancel the request
    var timeoutMs = (window.RivianTrackrAI.requestTimeout || 60) * 1000;
    var abortController = new AbortController();
    var progressTimers = [];
    var timeoutId = setTimeout(function() {
      abortController.abort();
      progressTimers.forEach(clearTimeout);
      renderError(container, 'Request timed out. Please refresh the page to try again.');
    }, timeoutMs);

    // Progressive status messages for slow responses. The status element is a
    // sibling of the (aria-hidden) skeleton so screen readers actually hear it.
    var progressMessages = [
      { delay: 10000, text: 'Still working on your summary...' },
      { delay: 20000, text: 'Taking a bit longer than usual...' },
      { delay: 30000, text: 'Almost there, please wait...' }
    ];
    progressTimers = progressMessages.map(function(msg) {
      return setTimeout(function() {
        if (!container.querySelector('.riviantrackr-skeleton')) return;
        var status = container.querySelector('.riviantrackr-skeleton-status');
        if (!status) {
          status = document.createElement('p');
          status.className = 'riviantrackr-skeleton-status';
          status.setAttribute('role', 'status');
          container.appendChild(status);
        }
        status.textContent = msg.text;
      }, msg.delay);
    });

    var fetchHeaders = {};
    if (window.RivianTrackrAI.nonce) {
      fetchHeaders['X-WP-Nonce'] = window.RivianTrackrAI.nonce;
    }

    fetch(endpoint, { credentials: 'same-origin', signal: abortController.signal, headers: fetchHeaders })
      .then(function(response) {
        progressTimers.forEach(clearTimeout);
        clearTimeout(timeoutId);

        // 429 / 403 carry a WP_Error body with a specific message; prefer it.
        if (response.status === 429 || response.status === 403) {
          var fallback = response.status === 429
            ? 'Too many requests. Please wait a moment and try again.'
            : 'Access denied. AI search is not available for this request.';
          return response.json().then(function(body) {
            return { error: (body && body.message) || fallback };
          }, function() {
            return { error: fallback };
          });
        }

        if (!response.ok) {
          throw new Error('Network response was not ok');
        }

        return response.json();
      })
      .then(function(data) {
        clearTimeout(timeoutId);

        if (data && data.answer_html) {
          // Cache successful responses
          saveToCache(q, data);
          renderAnswer(container, data.answer_html);
          return;
        }

        if (data && data.error) {
          // Cache no-results and off-topic responses so we don't re-hit the server
          var errorCodes = (window.RivianTrackrAI && window.RivianTrackrAI.errorCodes) || {};
          var cacheableErrors = [errorCodes.noResults || 'no_results', errorCodes.offTopic || 'off_topic'];
          if (cacheableErrors.indexOf(data.error_code) !== -1) {
            saveToCache(q, data);
          }
          renderError(container, data.error);
          return;
        }

        renderError(container, 'AI summary is not available right now.');
      })
      .catch(function(error) {
        clearTimeout(timeoutId);
        progressTimers.forEach(clearTimeout);
        // Don't show error if request was intentionally aborted (timeout already handled)
        if (error.name === 'AbortError') {
          return;
        }
        renderError(container, 'AI summary is not available right now.');
      });
  });
})();
