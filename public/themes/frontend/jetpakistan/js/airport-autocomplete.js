// JetPakistan — shared airport autocomplete (OTA /airports/search contract)
window.JpAirportAutocomplete = (function () {
  var DEBOUNCE_MS = 180;
  var MIN_QUERY = 2;
  var LIMIT = 10;
  var boundRoots = new WeakSet();

  function escText(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function itemHeadline(item, code) {
    var city = (item.city || '').trim();
    if (city) return city + ' (' + code + ')';
    var name = (item.name || '').trim();
    if (name) return name + ' (' + code + ')';
    var apiLabel = (item.label || '').trim();
    if (apiLabel) return apiLabel;
    return code;
  }

  function itemSubline(item) {
    var name = (item.name || '').trim();
    var city = (item.city || '').trim();
    var country = (item.country || '').trim();
    var parts = [];
    if (name && name !== city) parts.push(name);
    if (country) parts.push(country);
    if (parts.length) return parts.join(' · ');
    var description = (item.description || '').trim();
    if (description) return description;
    return '';
  }

  function hiddenForInput(input) {
    var field = input.closest('[data-jp-airport-field]');
    if (!field) return null;
    return field.querySelector('[data-jp-airport-code]');
  }

  function suggestBox(input) {
    var field = input.closest('[data-jp-airport-field]');
    if (!field) return null;
    return field.querySelector('.jp-airport-suggest');
  }

  function closeSuggest(box) {
    if (!box) return;
    box.innerHTML = '';
    box.hidden = true;
    var input = box.parentElement ? box.parentElement.querySelector('[data-jp-airport-input]') : null;
    if (input) input.setAttribute('aria-expanded', 'false');
  }

  function closeAll(root) {
    (root || document).querySelectorAll('.jp-airport-suggest').forEach(closeSuggest);
  }

  function renderSuggestions(input, items, onSelect) {
    var box = suggestBox(input);
    if (!box) return;
    box.innerHTML = '';
    if (!items.length) {
      box.innerHTML = '<div class="jp-airport-empty" role="status">No airports found</div>';
      box.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      return;
    }

    items.slice(0, LIMIT).forEach(function (item, index) {
      var code = (item.iata || item.iata_code || '').toUpperCase();
      if (!code) return;
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'jp-airport-item';
      row.setAttribute('role', 'option');
      row.setAttribute('data-iata', code);
      row.id = (input.id || 'jp-ac') + '-opt-' + index;
      row.innerHTML =
        '<span class="jp-airport-item-main">' + escText(itemHeadline(item, code)) + '</span>' +
        '<span class="jp-airport-item-sub">' + escText(itemSubline(item)) + '</span>';
      row.addEventListener('mousedown', function (e) {
        e.preventDefault();
        selectItem(input, item, code, onSelect);
      });
      box.appendChild(row);
    });
    box.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    input._jpAcItems = Array.prototype.slice.call(box.querySelectorAll('.jp-airport-item'));
    input._jpAcIndex = -1;
  }

  function selectItem(input, item, code, onSelect) {
    var hidden = hiddenForInput(input);
    input.value = itemHeadline(item, code);
    input.setAttribute('data-selected-iata', code);
    if (hidden) hidden.value = code;
    closeAll(input.closest('[data-jp-search]') || document);
    if (typeof onSelect === 'function') onSelect(input, code);
  }

  function wireInput(input, airportsUrl, onSelect) {
    if (!input || input.getAttribute('data-jp-ac-bound') === '1') return;
    input.setAttribute('data-jp-ac-bound', '1');

    var timer = null;
    var controller = null;

    function abort() {
      if (controller) controller.abort();
      controller = null;
    }

    function fetchSuggestions() {
      var query = (input.value || '').trim();
      if (query.length < MIN_QUERY) {
        abort();
        closeSuggest(suggestBox(input));
        return;
      }
      abort();
      controller = new AbortController();
      var box = suggestBox(input);
      if (box) {
        box.innerHTML = '<div class="jp-airport-loading" role="status">Searching…</div>';
        box.hidden = false;
      }
      fetch(airportsUrl + '?q=' + encodeURIComponent(query) + '&limit=' + LIMIT, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: controller.signal,
      })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (items) {
          if ((input.value || '').trim() !== query) return;
          renderSuggestions(input, Array.isArray(items) ? items : [], onSelect);
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          var failBox = suggestBox(input);
          if (failBox) {
            failBox.innerHTML = '<div class="jp-airport-error" role="alert">Could not load airports</div>';
            failBox.hidden = false;
          }
        });
    }

    input.addEventListener('input', function () {
      var hidden = hiddenForInput(input);
      if (input.getAttribute('data-selected-iata') && input.value.indexOf(input.getAttribute('data-selected-iata')) === -1) {
        input.removeAttribute('data-selected-iata');
        if (hidden) hidden.value = '';
      }
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(fetchSuggestions, DEBOUNCE_MS);
    });

    input.addEventListener('focus', function () {
      if ((input.value || '').trim().length >= MIN_QUERY) fetchSuggestions();
    });

    input.addEventListener('keydown', function (e) {
      var items = input._jpAcItems || [];
      var index = input._jpAcIndex != null ? input._jpAcIndex : -1;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!items.length) return;
        index = Math.min(index + 1, items.length - 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!items.length) return;
        index = Math.max(index - 1, 0);
      } else if (e.key === 'Enter') {
        if (index >= 0 && items[index]) {
          e.preventDefault();
          items[index].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        }
        return;
      } else if (e.key === 'Escape') {
        closeSuggest(suggestBox(input));
        return;
      } else {
        return;
      }
      input._jpAcIndex = index;
      items.forEach(function (row, i) {
        row.classList.toggle('is-active', i === index);
        if (i === index) row.scrollIntoView({ block: 'nearest' });
      });
    });
  }

  function init(root, options) {
    if (!root || boundRoots.has(root)) return;
    boundRoots.add(root);
    var airportsUrl = (options && options.airportsUrl) || root.getAttribute('data-airports-url') || '/airports/search';
    var onSelect = options && options.onSelect;

    function bindScope(scope) {
      (scope || root).querySelectorAll('[data-jp-airport-input]').forEach(function (input) {
        wireInput(input, airportsUrl, onSelect);
      });
    }

    bindScope(root);
    root._jpAirportBindScope = bindScope;
  }

  function bindDynamic(scope) {
    if (!scope) return;
    var root = scope.closest('[data-jp-search]');
    if (root && typeof root._jpAirportBindScope === 'function') {
      root._jpAirportBindScope(scope);
    }
  }

  return { init: init, bindDynamic: bindDynamic, closeAll: closeAll };
})();
