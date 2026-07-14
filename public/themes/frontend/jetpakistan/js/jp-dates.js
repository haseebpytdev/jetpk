// JetPakistan — date picker (DD MMM display, YYYY-MM-DD submit, return range overlay)
window.JpDates = (function () {
  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var boundRoots = new WeakSet();
  var overlay = null;
  var active = null;
  var globalBound = false;
  var suppressOutsideClose = false;

  function pad(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function toIso(y, m, d) {
    return y + '-' + pad(m + 1) + '-' + pad(d);
  }

  function parseIso(value) {
    if (!value) return null;
    var parts = String(value).split('-');
    if (parts.length !== 3) return null;
    return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
  }

  function formatDisplay(iso) {
    var dt = parseIso(iso);
    if (!dt) return '';
    return pad(dt.getDate()) + ' ' + MONTHS[dt.getMonth()];
  }

  function compareIso(a, b) {
    if (!a || !b) return 0;
    if (a < b) return -1;
    if (a > b) return 1;
    return 0;
  }

  function placeholderFor(field) {
    return field.getAttribute('data-jp-date-placeholder') || 'Date';
  }

  function getField(root, role) {
    return root.querySelector('[data-jp-date-role="' + role + '"]');
  }

  function getDepartIso(root) {
    var rangeField = getField(root, 'return_range');
    if (rangeField) {
      var rangeDepart = rangeField.querySelector('[data-jp-range-depart]');
      if (rangeDepart) return rangeDepart.value || '';
    }
    var field = getField(root, 'depart');
    var hidden = field ? field.querySelector('[data-jp-date-value]') : null;
    return hidden ? hidden.value : '';
  }

  function getReturnIso(root) {
    var rangeField = getField(root, 'return_range');
    if (rangeField) {
      var rangeReturn = rangeField.querySelector('[data-jp-range-return]');
      if (rangeReturn) return rangeReturn.value || '';
    }
    var field = getField(root, 'return');
    var hidden = field ? field.querySelector('[data-jp-date-value]') : null;
    return hidden ? hidden.value : '';
  }

  function isRoundTrip(root) {
    var trip = root.querySelector('[data-jp-trip-type]');
    return trip && trip.value === 'round_trip';
  }

  function syncRangeDisplay(rangeField, root) {
    if (!rangeField) return;
    var display = rangeField.querySelector('[data-jp-date-display]');
    if (!display) return;
    var ph = placeholderFor(rangeField);
    var departIso = getDepartIso(root);
    var returnIso = getReturnIso(root);
    if (departIso && returnIso) {
      display.textContent = formatDisplay(departIso) + ' - ' + formatDisplay(returnIso);
      display.classList.remove('is-placeholder');
    } else {
      display.textContent = ph;
      display.classList.add('is-placeholder');
    }
  }

  function setRangeDates(root, departIso, returnIso) {
    var rangeField = getField(root, 'return_range');
    if (!rangeField) {
      setFieldValue(getField(root, 'depart'), departIso);
      setFieldValue(getField(root, 'return'), returnIso);
      return;
    }
    var departHidden = rangeField.querySelector('[data-jp-range-depart]');
    var returnHidden = rangeField.querySelector('[data-jp-range-return]');
    if (departHidden) departHidden.value = departIso || '';
    if (returnHidden) returnHidden.value = returnIso || '';
    syncRangeDisplay(rangeField, root);
    rangeField.dispatchEvent(new CustomEvent('jp-date-change', {
      bubbles: true,
      detail: { value: departIso || '', return: returnIso || '' },
    }));
  }

  function setFieldValue(field, iso) {
    if (!field) return;
    var hidden = field.querySelector('[data-jp-date-value]');
    var display = field.querySelector('[data-jp-date-display]');
    var ph = placeholderFor(field);
    if (hidden) hidden.value = iso || '';
    if (display) {
      if (iso) {
        display.textContent = formatDisplay(iso);
        display.classList.remove('is-placeholder');
      } else {
        display.textContent = ph;
        display.classList.add('is-placeholder');
      }
    }
    field.dispatchEvent(new CustomEvent('jp-date-change', { bubbles: true, detail: { value: iso || '' } }));
  }

  function getMinForField(field, root) {
    var role = field.getAttribute('data-jp-date-role') || '';
    var hidden = field.querySelector('[data-jp-date-value]');
    var attrMin = hidden ? hidden.getAttribute('data-jp-date-min') : '';
    var rootMin = root.getAttribute('data-min-date') || '';

    if (role === 'return') {
      return getDepartIso(root) || attrMin || rootMin;
    }
    if (role === 'return_range') {
      return root.getAttribute('data-min-date') || attrMin || rootMin;
    }
    if (role === 'group_to') {
      var fromHidden = root.querySelector('[data-jp-date-role="group_from"] [data-jp-date-value]');
      return (fromHidden && fromHidden.value) || attrMin || rootMin;
    }
    if (role === 'multi_depart') {
      var seg = field.closest('[data-jp-multi-segment]');
      if (seg && seg.previousElementSibling) {
        var prevHidden = seg.previousElementSibling.querySelector('[data-jp-date-value]');
        if (prevHidden && prevHidden.value) return prevHidden.value;
      }
      return attrMin || rootMin;
    }
    return attrMin || rootMin;
  }

  function ensureOverlay() {
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.className = 'jp-date-overlay';
    overlay.setAttribute('data-jp-date-overlay', '');
    overlay.hidden = true;
    overlay.innerHTML =
      '<button type="button" class="jp-date-overlay__backdrop" data-jp-cal-close aria-label="Close calendar"></button>' +
      '<div class="jp-date-calendar" role="dialog" aria-modal="true" aria-label="Choose dates">' +
      '<div class="jp-date-calendar__top">' +
      '<div class="jp-date-cal-head">' +
      '<p class="jp-date-cal-hint" data-jp-cal-hint></p>' +
      '<p class="jp-date-cal-range" data-jp-cal-range hidden></p>' +
      '</div>' +
      '<button type="button" class="jp-date-cal-close" data-jp-cal-close aria-label="Close">&times;</button>' +
      '</div>' +
      '<div class="jp-date-calendar__body" data-jp-cal-body></div>' +
      '</div>';

    document.body.appendChild(overlay);

    overlay.querySelectorAll('[data-jp-cal-close]').forEach(function (btn) {
      btn.addEventListener('click', closeOverlay);
    });

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay.querySelector('.jp-date-overlay__backdrop')) closeOverlay();
    });

    return overlay;
  }

  function closeOverlay() {
    if (!overlay) return;
    overlay.hidden = true;
    overlay.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('jp-date-modal-open');
    if (active && active.trigger) active.trigger.setAttribute('aria-expanded', 'false');
    active = null;
  }

  function dayClasses(iso, state) {
    var classes = ['jp-date-cal-day'];
    var departIso = state.departIso || '';
    var returnIso = state.returnIso || '';
    var isRange = state.mode === 'range';

    if (iso === departIso) classes.push('is-start');
    if (iso === returnIso) classes.push('is-end');
    if (isRange && departIso && returnIso && compareIso(iso, departIso) > 0 && compareIso(iso, returnIso) < 0) {
      classes.push('is-in-range');
    }
    if (isRange && departIso && !returnIso && state.hoverIso && compareIso(iso, departIso) > 0 && compareIso(iso, state.hoverIso) <= 0) {
      classes.push('is-in-range');
    }
    if (iso === departIso && iso === returnIso) classes.push('is-same');

    return classes.join(' ');
  }

  function updateDayHighlights(state) {
    if (!overlay) return;
    overlay.querySelectorAll('[data-jp-cal-day]').forEach(function (btn) {
      var iso = btn.getAttribute('data-jp-cal-day');
      if (!iso) return;
      btn.className = dayClasses(iso, state);
    });
  }

  function updateRangeSummary(state) {
    if (!overlay) return;
    var rangeEl = overlay.querySelector('[data-jp-cal-range]');
    if (!rangeEl) return;
    var departIso = state.mode === 'range' ? getDepartIso(state.root) : '';
    var returnIso = state.mode === 'range' ? getReturnIso(state.root) : '';
    if (departIso && returnIso) {
      rangeEl.textContent = formatDisplay(departIso) + ' \u2013 ' + formatDisplay(returnIso);
      rangeEl.hidden = false;
    } else if (departIso) {
      rangeEl.textContent = formatDisplay(departIso) + ' \u2013 Select return';
      rangeEl.hidden = false;
    } else {
      rangeEl.hidden = true;
      rangeEl.textContent = '';
    }
  }

  function renderMonthGrid(grid, month, year, state) {
    grid.innerHTML = '';
    var first = new Date(year, month, 1);
    var startOffset = (first.getDay() + 6) % 7;
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var today = new Date();
    today = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    var departIso = state.departIso || '';
    var minIso = state.minIso || '';
    var isRange = state.mode === 'range';

    for (var i = 0; i < startOffset; i++) {
      var blank = document.createElement('span');
      blank.className = 'jp-date-cal-day jp-date-cal-day--blank';
      grid.appendChild(blank);
    }

    for (var day = 1; day <= daysInMonth; day++) {
      var iso = toIso(year, month, day);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = dayClasses(iso, state);
      btn.textContent = String(day);
      btn.setAttribute('data-jp-cal-day', iso);

      var dt = new Date(year, month, day);
      if (dt < today) btn.disabled = true;
      if (minIso && compareIso(iso, minIso) < 0) btn.disabled = true;

      grid.appendChild(btn);
    }
  }

  function monthPanel(month, year, state, showNav) {
    var panel = document.createElement('div');
    panel.className = 'jp-date-cal-month';

    var head = document.createElement('div');
    head.className = 'jp-date-cal-month__head';
    if (showNav) {
      head.innerHTML =
        '<button type="button" class="jp-date-cal-nav" data-jp-cal-prev aria-label="Previous month">&#8249;</button>' +
        '<span class="jp-date-cal-title" data-jp-cal-title>' + MONTH_NAMES[month] + ' ' + year + '</span>' +
        '<button type="button" class="jp-date-cal-nav" data-jp-cal-next aria-label="Next month">&#8250;</button>';
    } else {
      head.innerHTML = '<span class="jp-date-cal-title">' + MONTH_NAMES[month] + ' ' + year + '</span>';
    }
    panel.appendChild(head);

    var weekdays = document.createElement('div');
    weekdays.className = 'jp-date-calendar__weekdays';
    weekdays.setAttribute('aria-hidden', 'true');
    weekdays.innerHTML = '<span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span>';
    panel.appendChild(weekdays);

    var grid = document.createElement('div');
    grid.className = 'jp-date-calendar__grid';
    grid.setAttribute('data-jp-cal-grid', '');
    panel.appendChild(grid);

    renderMonthGrid(grid, month, year, state);
    return panel;
  }

  function shiftViewMonth(state, delta) {
    state.viewMonth += delta;
    if (state.viewMonth < 0) {
      state.viewMonth = 11;
      state.viewYear -= 1;
    } else if (state.viewMonth > 11) {
      state.viewMonth = 0;
      state.viewYear += 1;
    }
    syncActiveState(state);
    keepOverlayOpen();
    window.requestAnimationFrame(function () {
      if (active && active.state) repaint(active.state);
    });
  }

  function bindMonthNav(panel, state) {
    var prev = panel.querySelector('[data-jp-cal-prev]');
    var next = panel.querySelector('[data-jp-cal-next]');
    if (prev) {
      prev.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        shiftViewMonth(state, -1);
      });
    }
    if (next) {
      next.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        shiftViewMonth(state, 1);
      });
    }
  }

  function repaint(state) {
    if (!overlay || !active) return;
    var body = overlay.querySelector('[data-jp-cal-body]');
    var hint = overlay.querySelector('[data-jp-cal-hint]');
    var dialog = overlay.querySelector('.jp-date-calendar');
    if (!body || !dialog) return;

    state.departIso = state.mode === 'range' ? getDepartIso(state.root) : state.departIso;
    state.returnIso = state.mode === 'range' ? getReturnIso(state.root) : state.returnIso;

    if (state.mode === 'single') {
      state.minIso = getMinForField(state.field, state.root);
      if (hint) hint.textContent = 'Choose ' + placeholderFor(state.field).toLowerCase();
      dialog.classList.remove('is-range');
    } else {
      state.minIso = state.root.getAttribute('data-min-date') || '';
      dialog.classList.add('is-range');
      if (hint) {
        hint.textContent = state.phase === 'pick-end'
          ? 'Select your return date'
          : 'Select your departure date';
      }
    }

    updateRangeSummary(state);

    var dual = state.mode === 'range' && window.innerWidth >= 768;
    body.innerHTML = '';
    var monthsWrap = document.createElement('div');
    monthsWrap.className = 'jp-date-calendar__months' + (dual ? ' is-dual' : '');

    var m1 = state.viewMonth;
    var y1 = state.viewYear;
    var panel1 = monthPanel(m1, y1, state, true);
    monthsWrap.appendChild(panel1);

    if (dual) {
      var m2 = m1 + 1;
      var y2 = y1;
      if (m2 > 11) { m2 = 0; y2 += 1; }
      monthsWrap.appendChild(monthPanel(m2, y2, state, false));
    }

    body.appendChild(monthsWrap);

    bindMonthNav(panel1, state);

    body.querySelectorAll('[data-jp-cal-grid]').forEach(function (grid) {
      grid.addEventListener('mouseover', function (e) {
        var btn = e.target.closest('[data-jp-cal-day]');
        if (!btn || btn.disabled || state.mode !== 'range' || !state.departIso || state.returnIso) return;
        state.hoverIso = btn.getAttribute('data-jp-cal-day');
        updateDayHighlights(state);
      });
      grid.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-jp-cal-day]');
        if (!btn || btn.disabled) return;
        e.preventDefault();
        e.stopPropagation();
        handleDayClick(btn.getAttribute('data-jp-cal-day'), state);
      });
    });
  }

  function syncActiveState(state) {
    if (active) active.state = state;
  }

  function keepOverlayOpen() {
    suppressOutsideClose = true;
    window.setTimeout(function () {
      suppressOutsideClose = false;
    }, 0);
  }

  function handleDayClick(iso, state) {
    if (state.mode === 'single') {
      setFieldValue(state.field, iso);
      var role = state.field.getAttribute('data-jp-date-role') || '';
      if (window.JpForms) {
        if (role === 'group_from') window.JpForms.syncGroupDateRange(state.root);
        if (role === 'multi_depart') window.JpForms.syncMultiChronology(state.root);
      }
      closeOverlay();
      return;
    }

    var departField = getField(state.root, 'depart') || getField(state.root, 'return_range');
    var returnField = getField(state.root, 'return');
    var departIso = getDepartIso(state.root);
    var returnIso = getReturnIso(state.root);

    if (state.phase === 'pick-start' || !departIso) {
      setRangeDates(state.root, iso, '');
      state.phase = 'pick-end';
      state.hoverIso = null;
      state.departIso = iso;
      state.returnIso = '';
      syncActiveState(state);
      if (window.JpForms) window.JpForms.syncReturnMin(state.root);
      keepOverlayOpen();
      repaint(state);
      return;
    }

    if (compareIso(iso, departIso) < 0) {
      setRangeDates(state.root, iso, '');
      state.phase = 'pick-end';
      state.hoverIso = null;
      state.departIso = iso;
      state.returnIso = '';
      syncActiveState(state);
      if (window.JpForms) window.JpForms.syncReturnMin(state.root);
      keepOverlayOpen();
      repaint(state);
      return;
    }

    setRangeDates(state.root, departIso, iso);
    state.returnIso = iso;
    syncActiveState(state);
    if (window.JpForms) window.JpForms.syncReturnMin(state.root);
    closeOverlay();
  }

  function openOverlay(field, root) {
    var o = ensureOverlay();
    var trigger = field.querySelector('[data-jp-date-trigger]');
    var hidden = field.querySelector('[data-jp-date-value]');
    var role = field.getAttribute('data-jp-date-role') || '';
    var current = hidden ? hidden.value : '';
    var dt = parseIso(current) || parseIso(getMinForField(field, root)) || new Date();
    var rangeMode = isRoundTrip(root) && (role === 'depart' || role === 'return' || role === 'return_range');
    var existingDepart = getDepartIso(root);
    var existingReturn = getReturnIso(root);

    var phase = 'pick-start';
    if (rangeMode) {
      if ((role === 'return' || role === 'return_range') && existingDepart) {
        phase = 'pick-end';
      } else if (role === 'depart' || role === 'return_range') {
        phase = existingDepart && !existingReturn ? 'pick-end' : 'pick-start';
      }
    }

    var state = {
      root: root,
      field: field,
      mode: rangeMode ? 'range' : 'single',
      viewMonth: dt.getMonth(),
      viewYear: dt.getFullYear(),
      phase: phase,
      departIso: rangeMode ? existingDepart : '',
      returnIso: rangeMode ? existingReturn : '',
      minIso: '',
      hoverIso: null,
    };

    if (active && active.trigger) active.trigger.setAttribute('aria-expanded', 'false');
    active = { trigger: trigger, state: state };
    if (trigger) trigger.setAttribute('aria-expanded', 'true');

    o.hidden = false;
    o.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('jp-date-modal-open');
    repaint(state);
  }

  function wireField(field, root) {
    if (!field || field.getAttribute('data-jp-date-bound') === '1') return;
    field.setAttribute('data-jp-date-bound', '1');
    var trigger = field.querySelector('[data-jp-date-trigger]');
    if (field.getAttribute('data-jp-date-role') === 'return_range') {
      syncRangeDisplay(field, root);
    } else {
      var hidden = field.querySelector('[data-jp-date-value]');
      if (hidden && hidden.value) setFieldValue(field, hidden.value);
    }
    if (trigger) {
      trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        if (active && active.trigger === trigger) {
          closeOverlay();
          return;
        }
        closeOverlay();
        openOverlay(field, root);
      });
    }
  }

  function bindScope(scope, root) {
    (scope || root).querySelectorAll('[data-jp-date-field]').forEach(function (field) {
      wireField(field, root);
    });
  }

  function bindGlobalListeners() {
    if (globalBound) return;
    globalBound = true;

    document.addEventListener('click', function (e) {
      if (!active || suppressOutsideClose) return;
      if (overlay && overlay.contains(e.target)) return;
      if (e.target.closest('[data-jp-date-trigger]')) return;
      if (e.target.closest('[data-jp-cal-prev], [data-jp-cal-next]')) return;
      closeOverlay();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeOverlay();
    });
    window.addEventListener('resize', function () {
      if (active && active.state) repaint(active.state);
    }, { passive: true });
  }

  function init(root) {
    if (!root || boundRoots.has(root)) return;
    boundRoots.add(root);
    bindGlobalListeners();
    bindScope(root, root);
    root._jpDatesBindScope = function (scope) { bindScope(scope, root); };
  }

  function bindDynamic(scope) {
    var root = scope ? scope.closest('[data-jp-search]') : null;
    if (root && typeof root._jpDatesBindScope === 'function') root._jpDatesBindScope(scope);
  }

  function clearField(field) {
    if (!field) return;
    if (field.getAttribute('data-jp-date-role') === 'return_range') {
      var root = field.closest('[data-jp-search]');
      if (root) setRangeDates(root, '', '');
      return;
    }
    setFieldValue(field, '');
  }

  return {
    init: init,
    bindDynamic: bindDynamic,
    formatDisplay: formatDisplay,
    clearField: clearField,
    closeOverlay: closeOverlay,
    syncRangeField: function (root) {
      var rangeField = getField(root, 'return_range');
      if (rangeField) syncRangeDisplay(rangeField, root);
    },
  };
})();
