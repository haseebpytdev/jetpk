// JetPakistan — progressive AJAX login (HTML POST fallback when JS unavailable)
window.JpLogin = (function () {
  var GENERIC_RETRY = 'Something went wrong on our side. Please try again.';
  var NETWORK_ERROR = 'We could not reach the server. Check your connection and try again.';
  var SESSION_EXPIRED = 'Your session has expired. Please refresh the page and try again.';
  var RATE_LIMITED = 'Too many attempts. Please wait a moment and try again.';

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }

  function setFieldError(group, message) {
    if (!group) {
      return;
    }

    var errorEl = group.querySelector('[data-jp-field-error]');
    var input = group.querySelector('input, select, textarea');

    if (errorEl) {
      errorEl.textContent = message || '';
      errorEl.hidden = !message;
    }

    if (input) {
      input.classList.toggle('jp-input--invalid', !!message);
      if (message) {
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
      }
    }
  }

  function clearErrors(form) {
    var alert = form.querySelector('[data-jp-login-alert]');
    if (alert) {
      alert.textContent = '';
      alert.hidden = true;
    }

    form.querySelectorAll('[data-jp-field-group]').forEach(function (group) {
      setFieldError(group, null);
    });
  }

  function showFormAlert(form, message) {
    var alert = form.querySelector('[data-jp-login-alert]');
    if (!alert || !message) {
      return;
    }

    alert.textContent = message;
    alert.hidden = false;
  }

  function focusFirstIssue(form) {
    var invalidInput = form.querySelector('[aria-invalid="true"]');
    if (invalidInput) {
      invalidInput.focus();
      return;
    }

    var alert = form.querySelector('[data-jp-login-alert]');
    if (alert && !alert.hidden) {
      alert.focus();
    }
  }

  function setLoading(form, loading) {
    var submitBtn = form.querySelector('button[type="submit"]');
    form.setAttribute('aria-busy', loading ? 'true' : 'false');

    if (!submitBtn) {
      return;
    }

    if (!submitBtn.hasAttribute('data-jp-submit-label')) {
      submitBtn.setAttribute('data-jp-submit-label', submitBtn.textContent.trim());
    }

    var idleLabel = submitBtn.getAttribute('data-jp-submit-label') || 'Log in';
    var loadingLabel = submitBtn.getAttribute('data-jp-loading-label') || 'Logging in…';

    submitBtn.disabled = !!loading;
    submitBtn.setAttribute('aria-disabled', loading ? 'true' : 'false');
    submitBtn.classList.toggle('jp-btn--loading', !!loading);
    submitBtn.textContent = loading ? loadingLabel : idleLabel;
  }

  function applyValidationErrors(form, errors) {
    if (!errors || typeof errors !== 'object') {
      return;
    }

    var credentialMessage = null;

    Object.keys(errors).forEach(function (field) {
      var messages = errors[field];
      var message = Array.isArray(messages) ? messages[0] : messages;
      if (!message) {
        return;
      }

      if (field === 'login' || field === 'email') {
        credentialMessage = credentialMessage || message;
      }

      var group = form.querySelector('[data-jp-field-group="' + field + '"]');
      if (!group && (field === 'email' || field === 'login')) {
        group = form.querySelector('[data-jp-field-group="login"]');
      }

      setFieldError(group, message);
    });

    if (credentialMessage) {
      showFormAlert(form, credentialMessage);
    }

    focusFirstIssue(form);
  }

  function isSafeRedirect(path) {
    return typeof path === 'string'
      && path.charAt(0) === '/'
      && path.indexOf('//') !== 0
      && path.indexOf('\\') === -1;
  }

  function messageForStatus(status) {
    if (status === 419) {
      return SESSION_EXPIRED;
    }
    if (status === 429) {
      return RATE_LIMITED;
    }
    if (status >= 500) {
      return GENERIC_RETRY;
    }
  }

  function clearPassword(form) {
    var passwordInput = form.querySelector('input[name="password"]');
    if (passwordInput) {
      passwordInput.value = '';
    }
  }

  function handleSubmit(event) {
    var form = event.currentTarget;
    if (!form || form.getAttribute('data-jp-login-bound') !== '1') {
      return;
    }

    event.preventDefault();

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    if (form.getAttribute('data-jp-submitting') === '1') {
      return;
    }

    form.setAttribute('data-jp-submitting', '1');
    clearErrors(form);
    setLoading(form, true);

    var formData = new FormData(form);

    fetch(form.action, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: formData,
      credentials: 'same-origin',
    })
      .then(function (response) {
        var contentType = response.headers.get('content-type') || '';
        if (contentType.indexOf('application/json') === -1) {
          var statusMessage = messageForStatus(response.status);
          if (statusMessage) {
            showFormAlert(form, statusMessage);
            focusFirstIssue(form);
            clearPassword(form);
            return null;
          }

          throw new Error('unexpected_response');
        }

        return response.json().then(function (data) {
          return { response: response, data: data };
        });
      })
      .then(function (result) {
        if (!result) {
          return;
        }

        var response = result.response;
        var data = result.data || {};

        if (response.ok && data.ok === true && isSafeRedirect(data.redirect)) {
          window.location.assign(data.redirect);
          return;
        }

        if (response.status === 422 && data.errors) {
          applyValidationErrors(form, data.errors);
          clearPassword(form);
          return;
        }

        var fallbackMessage = data.message || messageForStatus(response.status) || NETWORK_ERROR;
        showFormAlert(form, fallbackMessage);
        focusFirstIssue(form);
        clearPassword(form);
      })
      .catch(function () {
        showFormAlert(form, NETWORK_ERROR);
        focusFirstIssue(form);
        clearPassword(form);
      })
      .finally(function () {
        setLoading(form, false);
        form.removeAttribute('data-jp-submitting');
      });
  }

  function init(root) {
    var scope = root || document;
    var form = scope.querySelector('[data-jp-login-form]');
    if (!form || form.getAttribute('data-jp-login-bound') === '1') {
      return;
    }

    form.setAttribute('data-jp-login-bound', '1');
    form.addEventListener('submit', handleSubmit);
  }

  return {
    init: init,
  };
})();

document.addEventListener('DOMContentLoaded', function () {
  if (window.JpLogin && typeof window.JpLogin.init === 'function') {
    window.JpLogin.init(document);
  }
});
