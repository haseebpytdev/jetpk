// JetPakistan — generic form helpers (dates, disabled fields)
window.JpForms = (function () {
  function setDisabled(container, disabled) {
    if (!container) return;
    container.querySelectorAll('input, select, textarea').forEach(function (el) {
      if (el.type === 'submit') return;
      el.disabled = !!disabled;
    });
    container.querySelectorAll('[data-jp-date-trigger]').forEach(function (btn) {
      btn.disabled = !!disabled;
    });
  }

  function syncReturnMin(root) {
    var departHidden = root.querySelector('[data-jp-date-role="depart"] [data-jp-date-value]')
      || root.querySelector('[data-jp-date-role="return_range"] [data-jp-range-depart]');
    var returnField = root.querySelector('[data-jp-return-field], [data-jp-date-role="return"]');
    var returnHidden = returnField ? returnField.querySelector('[data-jp-date-value]') : null;
    var rangeReturn = root.querySelector('[data-jp-date-role="return_range"] [data-jp-range-return]');
    if (!returnHidden && rangeReturn) returnHidden = rangeReturn;
    if (!departHidden || !returnHidden) return;
    var minDate = root.getAttribute('data-min-date') || '';
    var min = departHidden.value || minDate;
    returnHidden.setAttribute('data-jp-date-min', min);
    if (returnHidden.value && returnHidden.value < min) {
      if (rangeReturn) {
        rangeReturn.value = '';
        if (window.JpDates) window.JpDates.syncRangeField(root);
      } else if (window.JpDates && returnField) {
        window.JpDates.clearField(returnField);
      } else {
        returnHidden.value = min;
      }
    }
  }

  function syncGroupDateRange(root) {
    var from = root.querySelector('[data-jp-date-role="group_from"] [data-jp-date-value]');
    var toField = root.querySelector('[data-jp-date-role="group_to"]');
    var to = toField ? toField.querySelector('[data-jp-date-value]') : null;
    if (!from || !to) return;
    var minDate = root.getAttribute('data-min-date') || '';
    var min = from.value || minDate;
    to.setAttribute('data-jp-date-min', min);
    if (to.value && to.value < min) {
      if (window.JpDates && toField) window.JpDates.clearField(toField);
      else to.value = min;
    }
  }

  function syncMultiChronology(root) {
    var rows = root.querySelectorAll('[data-jp-multi-segment]');
    var minDate = root.getAttribute('data-min-date') || '';
    var prev = null;
    rows.forEach(function (row, index) {
      var field = row.querySelector('[data-jp-date-role="multi_depart"]');
      var input = field ? field.querySelector('[data-jp-date-value]') : null;
      if (!input) return;
      var min = index === 0 ? minDate : (prev && prev.value) || minDate;
      input.setAttribute('data-jp-date-min', min);
      if (input.value && input.value < min) {
        if (window.JpDates && field) window.JpDates.clearField(field);
        else input.value = min;
      }
      prev = input;
    });
  }

  function init(root) {
    if (!root || root.getAttribute('data-jp-forms-bound') === '1') return;
    root.setAttribute('data-jp-forms-bound', '1');

    root.addEventListener('jp-date-change', function (e) {
      var field = e.target.closest('[data-jp-date-field]');
      if (!field) return;
      var role = field.getAttribute('data-jp-date-role') || '';
      if (role === 'depart' || role === 'return' || role === 'return_range') syncReturnMin(root);
      if (role === 'group_from') syncGroupDateRange(root);
      if (role === 'multi_depart') syncMultiChronology(root);
    });

    syncReturnMin(root);
    syncGroupDateRange(root);
    syncMultiChronology(root);
  }

  function initOtpResendCountdown(root) {
    var scope = root || document;
    var form = scope.querySelector('.jp-auth-otp-resend');
    if (!form || form.getAttribute('data-jp-otp-bound') === '1') {
      return;
    }
    form.setAttribute('data-jp-otp-bound', '1');

    var btn = form.querySelector('[data-jp-otp-resend-btn]') || form.querySelector('button[type="submit"]');
    if (!btn) {
      return;
    }

    var seconds = parseInt(form.getAttribute('data-resend-seconds') || '0', 10);
    if (isNaN(seconds) || seconds < 0) {
      seconds = 0;
    }

    var intervalId = null;

    function render() {
      if (seconds > 0) {
        btn.disabled = true;
        btn.setAttribute('aria-disabled', 'true');
        btn.textContent = 'Resend code in ' + seconds + 's';
      } else {
        btn.disabled = false;
        btn.removeAttribute('aria-disabled');
        btn.textContent = 'Resend code';
        if (intervalId !== null) {
          window.clearInterval(intervalId);
          intervalId = null;
        }
      }
    }

    render();
    if (seconds > 0) {
      intervalId = window.setInterval(function () {
        seconds -= 1;
        render();
      }, 1000);
    }
  }

  return {
    init: init,
    setDisabled: setDisabled,
    syncReturnMin: syncReturnMin,
    syncGroupDateRange: syncGroupDateRange,
    syncMultiChronology: syncMultiChronology,
    initOtpResendCountdown: initOtpResendCountdown,
  };
})();

document.addEventListener('DOMContentLoaded', function () {
  if (window.JpForms && typeof window.JpForms.initOtpResendCountdown === 'function') {
    window.JpForms.initOtpResendCountdown(document);
  }
});
