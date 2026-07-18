// JetPakistan — passenger / cabin selector
window.JpPassengers = (function () {
  var boundRoots = new WeakSet();
  var openPicker = null;
  var globalBound = false;
  var MAX_TOTAL = 9;
  var MOBILE_MAX = 680;

  function cabinLabel(val) {
    var map = {
      economy: 'Economy',
      premium_economy: 'Premium Economy',
      business: 'Business',
      first: 'First',
    };
    return map[val] || 'Economy';
  }

  function getPanel(picker) {
    if (!picker) return null;
    var panel = picker.querySelector('[data-jp-pax-panel]');
    if (panel) {
      picker._jpPaxPanelRef = panel;
      return panel;
    }
    if (picker._jpPaxPanelRef && document.body.contains(picker._jpPaxPanelRef)) {
      return picker._jpPaxPanelRef;
    }
    var trigger = picker.querySelector('[data-jp-pax-trigger]');
    if (trigger) {
      var id = trigger.getAttribute('aria-controls');
      if (id) {
        panel = document.getElementById(id);
        if (panel) {
          picker._jpPaxPanelRef = panel;
          return panel;
        }
      }
    }
    return null;
  }

  function readCounts(picker) {
    var panel = getPanel(picker);
    var root = panel || picker;
    var adults = parseInt((root.querySelector('[data-jp-pax-input="adults"]') || {}).value, 10);
    var children = parseInt((root.querySelector('[data-jp-pax-input="children"]') || {}).value, 10);
    var infants = parseInt((root.querySelector('[data-jp-pax-input="infants"]') || {}).value, 10);
    return {
      adults: Number.isFinite(adults) ? adults : 1,
      children: Number.isFinite(children) ? children : 0,
      infants: Number.isFinite(infants) ? infants : 0,
    };
  }

  function totalPassengers(picker) {
    var counts = readCounts(picker);
    return counts.adults + counts.children + counts.infants;
  }

  function syncCompatSelects(picker) {
    var panel = getPanel(picker);
    if (!panel) return;
    ['adults', 'children', 'infants'].forEach(function (kind) {
      var compat = panel.querySelector('[data-jp-pax-compat-select="' + kind + '"]');
      var input = panel.querySelector('[data-jp-pax-input="' + kind + '"]');
      if (!compat || !input) return;
      compat.value = String(input.value);
    });
  }

  function applyCounts(picker, counts) {
    var panel = getPanel(picker);
    if (!panel) return;

    ['adults', 'children', 'infants'].forEach(function (kind) {
      var val = counts[kind];
      var input = panel.querySelector('[data-jp-pax-input="' + kind + '"]');
      var stepper = panel.querySelector('[data-jp-pax-stepper="' + kind + '"]');
      var countEl = stepper ? stepper.querySelector('[data-jp-pax-count]') : null;
      var compat = panel.querySelector('[data-jp-pax-compat-select="' + kind + '"]');
      if (input) input.value = String(val);
      if (countEl) countEl.textContent = String(val);
      if (compat) compat.value = String(val);
    });
  }

  function normalizeCounts(picker) {
    if (!picker) return false;
    var counts = readCounts(picker);
    var changed = false;

    if (counts.adults < 1) {
      counts.adults = 1;
      changed = true;
    }
    if (counts.infants > counts.adults) {
      counts.infants = counts.adults;
      changed = true;
    }

    var total = counts.adults + counts.children + counts.infants;
    if (total > MAX_TOTAL) {
      var over = total - MAX_TOTAL;
      if (counts.children >= over) {
        counts.children -= over;
      } else {
        over -= counts.children;
        counts.children = 0;
        counts.infants = Math.max(0, counts.infants - over);
      }
      changed = true;
    }

    if (counts.infants > counts.adults) {
      counts.infants = counts.adults;
      changed = true;
    }

    if (changed) applyCounts(picker, counts);
    syncInfantMax(picker);
    syncStepperLimits(picker);
    syncAllStepperButtons(picker);
    updateSummary(picker);
    return changed;
  }

  function syncStepperButtonStates(stepper, picker) {
    if (!stepper) return;
    picker = picker || resolvePicker(stepper);
    if (!picker) return;

    var kind = stepper.getAttribute('data-jp-pax-stepper');
    var counts = readCounts(picker);
    var current = counts[kind] || 0;
    var min = parseInt(stepper.getAttribute('data-min') || '0', 10);
    var max = parseInt(stepper.getAttribute('data-max') || String(MAX_TOTAL), 10);
    var atMin = current <= min;
    var atMax = current >= max;

    stepper.classList.toggle('is-at-min', atMin);
    stepper.classList.toggle('is-at-max', atMax);

    var dec = stepper.querySelector('[data-jp-pax-dec]');
    var inc = stepper.querySelector('[data-jp-pax-inc]');
    if (dec) dec.disabled = atMin;
    if (inc) inc.disabled = atMax;
  }

  function syncAllStepperButtons(picker) {
    if (!picker) return;
    var panel = getPanel(picker);
    if (!panel) return;
    syncStepperLimits(picker);
    panel.querySelectorAll('[data-jp-pax-stepper]').forEach(function (stepper) {
      syncStepperButtonStates(stepper, picker);
    });
  }

  function syncStepperLimits(picker) {
    var panel = getPanel(picker);
    if (!panel) return;
    var counts = readCounts(picker);
    var adultsStepper = panel.querySelector('[data-jp-pax-stepper="adults"]');
    var childrenStepper = panel.querySelector('[data-jp-pax-stepper="children"]');
    var infantsStepper = panel.querySelector('[data-jp-pax-stepper="infants"]');

    var adultsMin = Math.max(1, counts.infants);
    var adultsMax = Math.max(adultsMin, MAX_TOTAL - counts.children - counts.infants);
    var childrenMax = Math.max(0, MAX_TOTAL - counts.adults - counts.infants);
    var infantsMax = Math.max(0, Math.min(counts.adults, MAX_TOTAL - counts.adults - counts.children));

    if (adultsStepper) {
      adultsStepper.setAttribute('data-min', String(adultsMin));
      adultsStepper.setAttribute('data-max', String(adultsMax));
    }
    if (childrenStepper) {
      childrenStepper.setAttribute('data-min', '0');
      childrenStepper.setAttribute('data-max', String(childrenMax));
    }
    if (infantsStepper) {
      infantsStepper.setAttribute('data-min', '0');
      infantsStepper.setAttribute('data-max', String(infantsMax));
    }
  }

  function syncInfantMax(picker) {
    var panel = getPanel(picker);
    if (!panel) return;
    var counts = readCounts(picker);
    var infantsStepper = panel.querySelector('[data-jp-pax-stepper="infants"]');
    var infantsInput = panel.querySelector('[data-jp-pax-input="infants"]');
    if (!infantsStepper || !infantsInput) return;

    var infantsMax = Math.min(counts.adults, MAX_TOTAL - counts.adults - counts.children);
    infantsStepper.setAttribute('data-max', String(Math.max(0, infantsMax)));

    if (counts.infants > counts.adults) {
      counts.infants = counts.adults;
      infantsInput.value = String(counts.infants);
      var count = infantsStepper.querySelector('[data-jp-pax-count]');
      if (count) count.textContent = String(counts.infants);
    } else if (counts.infants > infantsMax) {
      infantsInput.value = String(infantsMax);
      var countEl = infantsStepper.querySelector('[data-jp-pax-count]');
      if (countEl) countEl.textContent = String(infantsMax);
    }
  }

  function updateSummary(picker) {
    var summary = picker.querySelector('[data-jp-pax-summary]');
    if (!summary) return;
    var counts = readCounts(picker);
    var panel = getPanel(picker);
    var cabinEl = panel ? panel.querySelector('[data-jp-pax-cabin]') : picker.querySelector('[data-jp-pax-cabin]');
    var cabin = (cabinEl && cabinEl.value) || 'economy';
    var text = counts.adults + ' adult' + (counts.adults === 1 ? '' : 's');
    if (counts.children > 0) text += ', ' + counts.children + ' child' + (counts.children === 1 ? '' : 'ren');
    if (counts.infants > 0) text += ', ' + counts.infants + ' infant' + (counts.infants === 1 ? '' : 's');
    text += ' \u00b7 ' + cabinLabel(cabin);
    summary.textContent = text;
  }

  function resolvePicker(stepper) {
    var picker = stepper.closest('[data-jp-pax-picker]');
    if (picker) return picker;
    var panel = stepper.closest('[data-jp-pax-panel]');
    if (panel && panel._jpPaxPicker) return panel._jpPaxPicker;
    return null;
  }

  function canSetStepperValue(stepper, next) {
    var picker = resolvePicker(stepper);
    if (!picker) return false;
    syncStepperLimits(picker);

    var kind = stepper.getAttribute('data-jp-pax-stepper');
    var min = parseInt(stepper.getAttribute('data-min') || '0', 10);
    var max = parseInt(stepper.getAttribute('data-max') || String(MAX_TOTAL), 10);
    var counts = readCounts(picker);
    var current = counts[kind] || 0;

    if (next < min || next > max) return false;

    var otherTotal = totalPassengers(picker) - current;
    if (otherTotal + next > MAX_TOTAL) return false;
    if (kind === 'adults' && next < Math.max(1, counts.infants)) return false;
    if (kind === 'infants' && next > counts.adults) return false;

    return true;
  }

  function setStepperValue(stepper, next) {
    var kind = stepper.getAttribute('data-jp-pax-stepper');
    var picker = resolvePicker(stepper);
    if (!picker) return next;

    if (!canSetStepperValue(stepper, next)) {
      syncAllStepperButtons(picker);
      return readCounts(picker)[kind] || 0;
    }

    syncStepperLimits(picker);

    var min = parseInt(stepper.getAttribute('data-min') || '0', 10);
    var max = parseInt(stepper.getAttribute('data-max') || String(MAX_TOTAL), 10);
    var counts = readCounts(picker);
    var current = counts[kind] || 0;

    next = Math.min(Math.max(next, min), max);

    var otherTotal = totalPassengers(picker) - current;
    if (otherTotal + next > MAX_TOTAL) {
      next = MAX_TOTAL - otherTotal;
    }

    if (kind === 'adults') {
      next = Math.max(next, Math.max(1, counts.infants));
    }
    if (kind === 'infants') {
      next = Math.min(next, counts.adults);
    }

    next = Math.min(Math.max(next, min), max);

    var panel = getPanel(picker);
    var input = panel ? panel.querySelector('[data-jp-pax-input="' + kind + '"]') : null;
    var count = stepper.querySelector('[data-jp-pax-count]');
    if (input) input.value = String(next);
    if (count) count.textContent = String(next);

    syncInfantMax(picker);
    syncStepperLimits(picker);
    syncAllStepperButtons(picker);
    updateSummary(picker);
    clearInlineError(picker);
    return next;
  }

  function restorePanelHome(panel) {
    if (!panel || !panel._jpPaxHome) return;
    if (panel.parentElement !== panel._jpPaxHome) {
      panel._jpPaxHome.appendChild(panel);
    }
    panel.hidden = true;
    panel.classList.remove('is-open', 'is-flip-above');
    panel.style.cssText = '';
  }

  function closeAllPanels() {
    document.querySelectorAll('[data-jp-pax-panel]').forEach(restorePanelHome);
    document.querySelectorAll('[data-pax-picker][open]').forEach(function (picker) {
      picker.removeAttribute('open');
    });
    if (openPicker) {
      var trigger = openPicker.querySelector('[data-jp-pax-trigger]');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
      openPicker.removeAttribute('open');
    }
    openPicker = null;
  }

  function isMobileViewport() {
    return window.innerWidth <= MOBILE_MAX;
  }

  function positionPanel(picker) {
    var trigger = picker.querySelector('[data-jp-pax-trigger]');
    var panel = getPanel(picker);
    if (!trigger || !panel || panel.hidden) return;

    panel._jpPaxHome = picker;
    panel._jpPaxPicker = picker;
    picker._jpPaxPanelRef = panel;

    if (panel.parentElement !== picker) {
      picker.appendChild(panel);
    }

    panel.style.position = '';
    panel.style.left = '';
    panel.style.right = '';
    panel.style.top = '';
    panel.style.bottom = '';
    panel.style.width = '';
    panel.style.maxHeight = '';
    panel.classList.add('is-open');

    var fieldRect = picker.getBoundingClientRect();
    var panelHeight = panel.offsetHeight || 280;
    var pad = 12;
    var spaceBelow = window.innerHeight - fieldRect.bottom - 6;
    var spaceAbove = fieldRect.top - 6;

    if (spaceBelow < panelHeight + pad && spaceAbove >= panelHeight + pad) {
      panel.classList.add('is-flip-above');
    } else {
      panel.classList.remove('is-flip-above');
    }
  }

  function openPanel(picker) {
    closeAllPanels();
    var trigger = picker.querySelector('[data-jp-pax-trigger]');
    var panel = getPanel(picker);
    if (!trigger || !panel) return;
    normalizeCounts(picker);
    panel.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    picker.setAttribute('open', '');
    openPicker = picker;
    positionPanel(picker);
    syncCompatSelects(picker);
  }

  function showInlineError(picker, message) {
    if (!picker) return;
    var err = picker.querySelector('[data-jp-pax-error]');
    if (!err) return;
    err.textContent = message;
    err.hidden = false;
    picker.classList.add('has-pax-error');
  }

  function clearInlineError(picker) {
    if (!picker) return;
    var err = picker.querySelector('[data-jp-pax-error]');
    if (err) {
      err.textContent = '';
      err.hidden = true;
    }
    picker.classList.remove('has-pax-error');
  }

  function validatePicker(picker) {
    if (!picker) return { ok: true };
    normalizeCounts(picker);
    var counts = readCounts(picker);
    var total = counts.adults + counts.children + counts.infants;

    if (counts.adults < 1) {
      return { ok: false, message: 'At least 1 adult is required.' };
    }
    if (counts.infants > counts.adults) {
      return { ok: false, message: 'Infants cannot exceed adults.' };
    }
    if (total > MAX_TOTAL) {
      return { ok: false, message: 'Maximum 9 passengers allowed.' };
    }
    clearInlineError(picker);
    return { ok: true };
  }

  function validateForm(form) {
    if (!form) return { ok: true };
    var picker = form.querySelector('[data-jp-pax-picker]');
    if (!picker) return { ok: true };
    var result = validatePicker(picker);
    if (!result.ok) {
      showInlineError(picker, result.message);
      var trigger = picker.querySelector('[data-jp-pax-trigger]');
      if (trigger) trigger.focus();
    }
    return result;
  }

  function wirePicker(picker) {
    if (!picker || picker.getAttribute('data-jp-pax-bound') === '1') return;
    picker.setAttribute('data-jp-pax-bound', '1');

    var trigger = picker.querySelector('[data-jp-pax-trigger]');
    var panel = picker.querySelector('[data-jp-pax-panel]');
    if (panel) {
      panel._jpPaxHome = picker;
      panel._jpPaxPicker = picker;
      picker._jpPaxPanelRef = panel;
    }

    if (trigger && panel) {
      trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        if (openPicker === picker && panel && !panel.hidden) {
          closeAllPanels();
          return;
        }
        openPanel(picker);
      });
    }

    var panelRoot = panel || getPanel(picker);
    if (!panelRoot) return;

    panelRoot.querySelectorAll('[data-jp-pax-stepper]').forEach(function (stepper) {
      stepper.querySelectorAll('[data-jp-pax-inc],[data-jp-pax-dec]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          if (btn.disabled) return;

          var kind = stepper.getAttribute('data-jp-pax-stepper');
          var pickerRef = resolvePicker(stepper);
          if (!pickerRef) return;

          var panelRef = getPanel(pickerRef);
          var input = panelRef ? panelRef.querySelector('[data-jp-pax-input="' + kind + '"]') : null;
          var countEl = stepper.querySelector('[data-jp-pax-count]');
          var current = parseInt((input && input.value) || (countEl && countEl.textContent) || '0', 10);
          var delta = btn.hasAttribute('data-jp-pax-inc') ? 1 : -1;
          var next = current + delta;

          if (!canSetStepperValue(stepper, next)) {
            syncAllStepperButtons(pickerRef);
            return;
          }

          setStepperValue(stepper, next);
        });
      });
    });

    var cabin = panelRoot.querySelector('[data-jp-pax-cabin]');
    if (cabin) {
      cabin.addEventListener('change', function () {
        updateSummary(picker);
        clearInlineError(picker);
      });
    }

    panelRoot.querySelectorAll('[data-jp-pax-compat-select]').forEach(function (compat) {
      compat.addEventListener('change', function () {
        var kind = compat.getAttribute('data-jp-pax-compat-select');
        if (!kind) return;
        var stepper = panelRoot.querySelector('[data-jp-pax-stepper="' + kind + '"]');
        var next = parseInt(compat.value, 10);
        if (!stepper || !Number.isFinite(next)) return;
        if (!canSetStepperValue(stepper, next)) {
          syncCompatSelects(picker);
          return;
        }
        setStepperValue(stepper, next);
      });
    });

    normalizeCounts(picker);
    syncCompatSelects(picker);
  }

  function bindGlobalListeners() {
    if (globalBound) return;
    globalBound = true;

    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-jp-pax-picker]') || e.target.closest('[data-jp-pax-panel]')) return;
      closeAllPanels();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAllPanels();
    });

    window.addEventListener('resize', function () {
      if (!openPicker) return;
      if (isMobileViewport()) {
        positionPanel(openPicker);
      } else {
        closeAllPanels();
      }
    }, { passive: true });

    window.addEventListener('scroll', function () {
      if (!openPicker || isMobileViewport()) return;
      positionPanel(openPicker);
    }, { passive: true, capture: true });
  }

  function init(root) {
    if (!root || boundRoots.has(root)) return;
    boundRoots.add(root);
    bindGlobalListeners();
    root.querySelectorAll('[data-jp-pax-picker]').forEach(wirePicker);
  }

  return {
    init: init,
    wirePicker: wirePicker,
    closeAll: closeAllPanels,
    normalizeCounts: normalizeCounts,
    validateForm: validateForm,
    reposition: function () {
      if (openPicker) positionPanel(openPicker);
    },
  };
})();
