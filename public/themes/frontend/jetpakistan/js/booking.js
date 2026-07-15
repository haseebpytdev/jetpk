/**
 * JetPakistan checkout — passenger account panel, support sanitization, payment cards.
 */
(function () {
  'use strict';

  function initCreateAccountPanel(root) {
    var scope = root || document;
    var cb = scope.querySelector('#checkout-create-account');
    var box = scope.querySelector('#checkout-inline-account-fields');
    var pwd = scope.querySelector('#checkout-password');
    var pwdConfirm = scope.querySelector('#checkout-password-confirm');
    var mismatch = scope.querySelector('#checkout-password-mismatch');

    if (!cb || !box) {
      return;
    }

    function clearPasswordMismatchUi() {
      if (mismatch) {
        mismatch.hidden = true;
      }
      if (pwdConfirm) {
        pwdConfirm.classList.remove('is-invalid');
        pwdConfirm.setAttribute('aria-invalid', 'false');
      }
      if (pwd) {
        pwd.classList.remove('is-invalid');
        pwd.setAttribute('aria-invalid', 'false');
      }
    }

    function syncPasswordMismatch() {
      if (!pwd || !pwdConfirm || !mismatch || !cb.checked) {
        clearPasswordMismatchUi();
        return;
      }
      var a = pwd.value;
      var b = pwdConfirm.value;
      var show = a !== '' && b !== '' && a !== b;
      mismatch.hidden = !show;
      pwdConfirm.classList.toggle('is-invalid', show);
      pwdConfirm.setAttribute('aria-invalid', show ? 'true' : 'false');
      pwd.classList.toggle('is-invalid', show);
      pwd.setAttribute('aria-invalid', show ? 'true' : 'false');
    }

    function syncAccountPanel() {
      var open = cb.checked;
      box.classList.toggle('is-open', open);
      box.setAttribute('aria-hidden', open ? 'false' : 'true');
      cb.setAttribute('aria-expanded', open ? 'true' : 'false');
      box.hidden = !open;

      if (pwd) {
        pwd.required = open;
        if (!open) {
          pwd.value = '';
        }
      }
      if (pwdConfirm) {
        pwdConfirm.required = open;
        if (!open) {
          pwdConfirm.value = '';
        }
      }
      if (!open) {
        clearPasswordMismatchUi();
      } else {
        syncPasswordMismatch();
      }
    }

    if (!cb.dataset.jpAccountBound) {
      cb.dataset.jpAccountBound = '1';
      cb.addEventListener('change', syncAccountPanel);
    }
    if (pwd && pwdConfirm) {
      pwd.addEventListener('input', syncPasswordMismatch);
      pwdConfirm.addEventListener('input', syncPasswordMismatch);
    }
    syncAccountPanel();
  }

  function sanitizeSupportCards(root) {
    var scope = root || document;
    scope.querySelectorAll('.ota-checkout-wa').forEach(function (card) {
      var phone = card.querySelector('.ota-checkout-wa-phone');
      if (phone && /^(123|\+92\s*300\s*0{6})$/i.test((phone.textContent || '').trim())) {
        phone.remove();
      }
      var waBtn = card.querySelector('.ota-btn-wa');
      var placeholderNote = card.querySelector('.text-muted');
      if (placeholderNote && /not configured/i.test(placeholderNote.textContent || '')) {
        placeholderNote.textContent = 'Support contact is not configured yet.';
      }
      if (!waBtn && !card.querySelector('.jp-checkout-support-email')) {
        var emailLink = card.querySelector('a[href^="mailto:"]');
        if (!emailLink) {
          var supportCard = scope.querySelector('[data-jp-support-card] a[href^="mailto:"]');
          if (supportCard) {
            var link = document.createElement('a');
            link.className = 'ota-btn-wa jp-checkout-support-email';
            link.href = supportCard.href;
            link.textContent = 'Email support';
            card.appendChild(link);
          }
        }
      }
    });
  }

  function enhanceFareFamilyBlocks(root) {
    var scope = root || document;
    scope.querySelectorAll('.ota-checkout-selected-fare-family__dl').forEach(function (dl) {
      if (dl.classList.contains('jp-kv-grid')) {
        return;
      }
      dl.classList.add('jp-kv-grid', 'jp-kv-grid--fare-family');
      dl.querySelectorAll('.ota-fare-dl__row').forEach(function (row) {
        row.classList.add('jp-kv-grid__row');
      });
    });
  }

  function initPaymentMethodCards(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-jp-payment-options] .ota-method-card__input').forEach(function (input) {
      var card = input.closest('.ota-method-card');
      if (!card || card.dataset.jpMethodBound) {
        return;
      }
      card.dataset.jpMethodBound = '1';
      function syncSelected() {
        scope.querySelectorAll('[data-jp-payment-options] .ota-method-card').forEach(function (el) {
          el.classList.toggle('is-selected', !!el.querySelector('.ota-method-card__input:checked'));
        });
      }
      input.addEventListener('change', syncSelected);
      syncSelected();
    });
  }

  function initCheckoutBody(root) {
    var scope = root || document;
    initCreateAccountPanel(scope);
    sanitizeSupportCards(scope);
    enhanceFareFamilyBlocks(scope);
    initPaymentMethodCards(scope);

    scope.querySelectorAll('.ota-checkout-summary-card').forEach(function (card) {
      if (card.querySelector('.jp-checkout-fare-notice, .ota-checkout-fare-notice')) {
        return;
      }
      var notice = document.createElement('p');
      notice.className = 'ota-checkout-fare-notice jp-checkout-fare-notice';
      notice.textContent = 'Final fare and price will be confirmed during airline price validation.';
      var anchor = card.querySelector('.ota-checkout-sidebar-block--fare') || card.querySelector('.ota-checkout-trip-summary__fare') || card;
      anchor.appendChild(notice);
    });
  }

  var scope = document.querySelector('[data-jp-checkout-body]') || document;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initCheckoutBody(document.querySelector('[data-jp-checkout-body]') || document);
    });
  } else {
    initCheckoutBody(scope);
  }
})();
