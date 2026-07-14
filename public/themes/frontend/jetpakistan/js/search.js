// JetPakistan — homepage search orchestration
(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  function moveIndicator(seg) {
    if (!seg) return;
    var ind = seg.querySelector('.pill-ind');
    var on = seg.querySelector('button.on');
    if (!ind || !on) return;
    ind.style.left = on.offsetLeft + 'px';
    ind.style.width = on.offsetWidth + 'px';
  }

  function activateButton(button, selector) {
    var group = button.closest('.seg');
    if (!group) return;
    group.querySelectorAll(selector || 'button').forEach(function (item) {
      item.classList.toggle('on', item === button);
      if (item.hasAttribute('aria-selected')) {
        item.setAttribute('aria-selected', item === button ? 'true' : 'false');
      }
    });
    moveIndicator(group);
  }

  function initRoot(root) {
    if (!root || root.getAttribute('data-jp-search-ready') === 'true') return;
    root.setAttribute('data-jp-search-ready', 'true');

    var minSegments = parseInt(root.getAttribute('data-min-segments') || '2', 10);
    var maxSegments = parseInt(root.getAttribute('data-max-segments') || '6', 10);
    var form = root.querySelector('[data-jp-flight-form]');
    var tripInput = root.querySelector('[data-jp-trip-type]');
    var simple = root.querySelector('[data-jp-simple-fields]');
    var multi = root.querySelector('[data-jp-multi-fields]');
    var returnField = root.querySelector('[data-jp-return-field], [data-jp-date-role="return"]');
    var returnInput = returnField ? returnField.querySelector('[data-jp-date-value]') : null;
    var onewayDepartField = root.querySelector('[data-jp-date-role="depart"]');
    var rangeField = root.querySelector('[data-jp-date-role="return_range"]');
    var onewayDepartInput = onewayDepartField ? onewayDepartField.querySelector('[data-jp-date-value]') : null;
    var rangeDepartInput = rangeField ? rangeField.querySelector('[data-jp-range-depart]') : null;
    var rangeReturnInput = rangeField ? rangeField.querySelector('[data-jp-range-return]') : null;
    var tripTabs = root.querySelector('#segTrip');
    var multiRows = root.querySelector('[data-jp-multi-rows]');
    var multiTpl = root.querySelector('template[id$="-multi-segment-tpl"]');

    if (window.JpForms) window.JpForms.init(root);
    if (window.JpDates) window.JpDates.init(root);
    if (window.JpAirportAutocomplete) {
      window.JpAirportAutocomplete.init(root, {
        onSelect: function (input) {
          var role = input.getAttribute('data-jp-airport-display');
          if (role === 'from') {
            var to = root.querySelector('[data-jp-airport-display="to"]');
            if (to) to.focus();
          } else if (role === 'to') {
            var departTrigger = root.querySelector('[data-jp-date-role="depart"] [data-jp-date-trigger]');
            if (departTrigger) departTrigger.focus();
          }
        },
      });
    }
    if (window.JpPassengers) window.JpPassengers.init(root);

    function mountFlightChrome(isMulti) {
      if (window.JpPassengers) window.JpPassengers.closeAll();

      var pax = root.querySelector('[data-jp-pax-picker]');
      var submit = root.querySelector('[data-jp-submit-field]');
      var slotRowPax = root.querySelector('[data-jp-pax-slot-row]');
      var slotRowSubmit = root.querySelector('[data-jp-submit-slot-row]');
      var slotActionSubmit = root.querySelector('[data-jp-submit-slot-action]');
      var slotMultiPax = root.querySelector('[data-jp-pax-slot-multi]');
      var slotMultiSubmit = root.querySelector('[data-jp-submit-slot-multi]');
      var footer = root.querySelector('[data-jp-multi-footer]');
      var actionRow = root.querySelector('[data-jp-search-action-row]');

      if (pax) {
        if (isMulti && slotMultiPax) slotMultiPax.appendChild(pax);
        else if (slotRowPax) slotRowPax.appendChild(pax);
        if (window.JpPassengers) window.JpPassengers.normalizeCounts(pax);
      }
      if (submit) {
        if (isMulti && slotMultiSubmit) slotMultiSubmit.appendChild(submit);
        else if (slotActionSubmit) slotActionSubmit.appendChild(submit);
        else if (slotRowSubmit) slotRowSubmit.appendChild(submit);
      }
      if (footer) footer.hidden = !isMulti;
      if (actionRow) actionRow.hidden = isMulti;
    }

    function wireSearchChecks() {
      root.querySelectorAll('[data-jp-search-checks] .check').forEach(function (label) {
        if (label.getAttribute('data-jp-check-bound') === '1') return;
        label.setAttribute('data-jp-check-bound', '1');
        var input = label.querySelector('input[type="checkbox"]');
        if (!input) return;
        label.classList.toggle('on', input.checked);
        label.addEventListener('click', function (e) {
          if (e.target === input) return;
          e.preventDefault();
          input.checked = !input.checked;
          label.classList.toggle('on', input.checked);
        });
        input.addEventListener('change', function () {
          label.classList.toggle('on', input.checked);
        });
      });
    }

    function syncDateFieldsForTrip(isRound) {
      if (onewayDepartField) onewayDepartField.hidden = isRound;
      if (rangeField) rangeField.hidden = !isRound;

      if (isRound) {
        if (onewayDepartInput) onewayDepartInput.removeAttribute('name');
        if (rangeDepartInput) rangeDepartInput.setAttribute('name', 'depart');
        if (rangeReturnInput) rangeReturnInput.setAttribute('name', 'return_date');
        if (onewayDepartInput && onewayDepartInput.value && rangeDepartInput && !rangeDepartInput.value) {
          rangeDepartInput.value = onewayDepartInput.value;
        }
      } else {
        if (onewayDepartInput) onewayDepartInput.setAttribute('name', 'depart');
        if (rangeDepartInput) rangeDepartInput.removeAttribute('name');
        if (rangeReturnInput) rangeReturnInput.removeAttribute('name');
        if (rangeDepartInput && rangeDepartInput.value && onewayDepartInput) {
          onewayDepartInput.value = rangeDepartInput.value;
          if (window.JpDates && onewayDepartField) {
            var display = onewayDepartField.querySelector('[data-jp-date-display]');
            if (display) {
              display.textContent = window.JpDates.formatDisplay(rangeDepartInput.value);
              display.classList.remove('is-placeholder');
            }
          }
        }
        if (rangeReturnInput && rangeReturnInput.value) {
          if (window.JpDates && rangeField) window.JpDates.clearField(rangeField);
          else rangeReturnInput.value = '';
        }
      }
      if (window.JpDates) window.JpDates.syncRangeField(root);
      if (window.JpForms) window.JpForms.syncReturnMin(root);
    }

    function setTrip(type) {
      var isMulti = type === 'multi_city';
      var isRound = type === 'round_trip';
      if (tripInput) tripInput.value = type;
      mountFlightChrome(isMulti);
      if (simple) simple.hidden = isMulti;
      if (multi) multi.hidden = !isMulti;
      syncDateFieldsForTrip(isRound);
      if (returnField) returnField.hidden = !isRound;
      if (returnInput) {
        if (!isRound) {
          if (window.JpDates) window.JpDates.clearField(returnField);
          else returnInput.value = '';
          returnInput.removeAttribute('name');
        } else {
          returnInput.setAttribute('name', 'return_date');
        }
      }
      if (window.JpForms) {
        window.JpForms.setDisabled(simple, isMulti);
        window.JpForms.setDisabled(multi, !isMulti);
        window.JpForms.syncReturnMin(root);
        window.JpForms.syncMultiChronology(root);
      }
      if (form) {
        form.classList.toggle('is-multi-city', isMulti);
        form.classList.toggle('is-one-way', type === 'one_way');
        form.classList.toggle('is-round-trip', isRound);
      }
    }

    function setProduct(product) {
      root.querySelectorAll('[data-jp-panel]').forEach(function (panel) {
        var active = panel.getAttribute('data-jp-panel') === product;
        panel.hidden = !active;
        if (window.JpForms) window.JpForms.setDisabled(panel, !active);
      });
      if (tripTabs) tripTabs.hidden = product !== 'flights';
      if (tripTabs && product === 'flights') moveIndicator(tripTabs);
    }

    function reindexMultiSegments() {
      if (!multiRows) return;
      multiRows.querySelectorAll('[data-jp-multi-segment]').forEach(function (row, index) {
        var num = index + 1;
        row.setAttribute('data-segment-index', String(num));
        var badge = row.querySelector('.jp-multi-segment-badge');
        if (badge) badge.textContent = 'Segment ' + num;
        var removeBtn = row.querySelector('[data-jp-multi-remove]');
        if (removeBtn) removeBtn.hidden = num <= minSegments;
      });
      if (window.JpForms) window.JpForms.syncMultiChronology(root);
    }

    function addMultiSegment() {
      if (!multiRows || !multiTpl) return;
      var count = multiRows.querySelectorAll('[data-jp-multi-segment]').length;
      if (count >= maxSegments) return;
      var html = multiTpl.innerHTML.replace(/__INDEX__/g, String(count + 1));
      var wrap = document.createElement('div');
      wrap.innerHTML = html.trim();
      var row = wrap.firstElementChild;
      if (!row) return;
      multiRows.appendChild(row);
      if (window.JpAirportAutocomplete) window.JpAirportAutocomplete.bindDynamic(row);
      if (window.JpDates) window.JpDates.bindDynamic(row);
      reindexMultiSegments();
    }

    function removeMultiSegment(button) {
      if (!multiRows) return;
      var rows = multiRows.querySelectorAll('[data-jp-multi-segment]');
      if (rows.length <= minSegments) return;
      var row = button.closest('[data-jp-multi-segment]');
      if (row) row.remove();
      reindexMultiSegments();
    }

    function prepareSubmit(e) {
      if (window.JpPassengers) {
        var paxResult = window.JpPassengers.validateForm(form);
        if (!paxResult.ok) {
          e.preventDefault();
          return false;
        }
      }

      var type = tripInput ? tripInput.value : 'one_way';
      if (type === 'one_way') {
        if (rangeReturnInput) rangeReturnInput.value = '';
        if (rangeReturnInput) rangeReturnInput.removeAttribute('name');
        if (returnInput) {
          if (window.JpDates && returnField) window.JpDates.clearField(returnField);
          else returnInput.value = '';
          returnInput.removeAttribute('name');
        }
      }
      if (type === 'multi_city' && simple) {
        simple.querySelectorAll('input, select').forEach(function (el) {
          if (el.type !== 'hidden' || el.name === 'trip_type') return;
          el.disabled = true;
        });
      }
    }

    var swap = root.querySelector('[data-jp-swap]');
    if (swap) {
      swap.addEventListener('click', function () {
        var fromDisplay = root.querySelector('[data-jp-airport-display="from"]');
        var toDisplay = root.querySelector('[data-jp-airport-display="to"]');
        var fromCode = root.querySelector('[data-jp-airport-code="from"]');
        var toCode = root.querySelector('[data-jp-airport-code="to"]');
        if (fromDisplay && toDisplay) {
          var dv = fromDisplay.value;
          fromDisplay.value = toDisplay.value;
          toDisplay.value = dv;
        }
        if (fromCode && toCode) {
          var cv = fromCode.value;
          fromCode.value = toCode.value;
          toCode.value = cv;
        }
        swap.classList.toggle('spin');
      });
    }

    root.querySelectorAll('[data-jp-product]').forEach(function (button) {
      button.addEventListener('click', function () {
        activateButton(button);
        setProduct(button.getAttribute('data-jp-product') || 'flights');
      });
    });

    root.querySelectorAll('[data-jp-trip]').forEach(function (button) {
      button.addEventListener('click', function () {
        activateButton(button);
        setTrip(button.getAttribute('data-jp-trip') || 'round_trip');
      });
    });

    root.querySelectorAll('[data-jp-multi-add]').forEach(function (btn) {
      btn.addEventListener('click', addMultiSegment);
    });

    root.addEventListener('click', function (e) {
      var remove = e.target.closest('[data-jp-multi-remove]');
      if (remove) removeMultiSegment(remove);
    });

    if (form) {
      form.addEventListener('submit', prepareSubmit);
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest('[data-jp-airport-field]')) {
        if (window.JpAirportAutocomplete) window.JpAirportAutocomplete.closeAll(root);
      }
    });

    root.querySelectorAll('.seg').forEach(moveIndicator);
    wireSearchChecks();
    setProduct('flights');
    setTrip(root.getAttribute('data-default-trip') || 'round_trip');
    reindexMultiSegments();

    window.addEventListener('resize', function () {
      root.querySelectorAll('.seg').forEach(moveIndicator);
    }, { passive: true });
  }

  ready(function () {
    document.querySelectorAll('[data-jp-search]').forEach(initRoot);
  });
})();
