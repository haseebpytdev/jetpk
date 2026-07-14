(function () {
  var cfg = window.__jpLogoBackground || {};
  var root = document.querySelector('[data-jp-logo-background]');
  if (!root || !cfg.stageUrl) return;

  var fileInput = root.querySelector('[data-jp-logo-file]');
  var panel = root.querySelector('[data-jp-logo-bg-panel]');
  var toggle = root.querySelector('[data-jp-logo-bg-toggle]');
  var privacy = root.querySelector('[data-jp-logo-bg-privacy]');
  var previews = root.querySelector('[data-jp-logo-bg-previews]');
  var originalImg = root.querySelector('[data-jp-logo-bg-original-preview]');
  var processedWrap = root.querySelector('[data-jp-logo-bg-processed-wrap]');
  var processedImg = root.querySelector('[data-jp-logo-bg-processed-preview]');
  var processedWhite = root.querySelector('[data-jp-logo-bg-processed-white]');
  var processedDark = root.querySelector('[data-jp-logo-bg-processed-dark]');
  var statusEl = root.querySelector('[data-jp-logo-bg-status]');
  var btnProcess = root.querySelector('[data-jp-logo-bg-process]');
  var btnAccept = root.querySelector('[data-jp-logo-bg-accept]');
  var btnKeep = root.querySelector('[data-jp-logo-bg-keep]');
  var btnRetry = root.querySelector('[data-jp-logo-bg-retry]');
  var btnCancel = root.querySelector('[data-jp-logo-bg-cancel]');

  var currentUuid = null;

  function setStatus(text) {
    if (statusEl) statusEl.textContent = text || '';
  }

  function showProcessed(url) {
    if (!url) return;
    [processedImg, processedWhite, processedDark].forEach(function (img) {
      if (img) img.src = url;
    });
    if (processedWrap) processedWrap.hidden = false;
  }

  function resetActions() {
    [btnAccept, btnKeep, btnRetry, btnCancel].forEach(function (btn) {
      if (btn) btn.hidden = true;
    });
    if (btnProcess) btnProcess.hidden = false;
  }

  fileInput?.addEventListener('change', function () {
    var file = fileInput.files && fileInput.files[0];
    if (!file) return;
    if (previews) previews.hidden = false;
    if (originalImg) originalImg.src = URL.createObjectURL(file);
    currentUuid = null;
    if (processedWrap) processedWrap.hidden = true;
    resetActions();
    setStatus('');
  });

  toggle?.addEventListener('change', function () {
    if (privacy) privacy.hidden = !toggle.checked;
  });

  function apiUrl(path) {
    return path;
  }

  function postJson(url, body) {
    var headers = {
      'X-CSRF-TOKEN': cfg.csrf,
      'Accept': 'application/json',
    };
    if (body instanceof FormData) {
      return fetch(url, { method: 'POST', headers: headers, body: body });
    }
    headers['Content-Type'] = 'application/json';
    return fetch(url, { method: 'POST', headers: headers, body: JSON.stringify(body || {}) });
  }

  btnProcess?.addEventListener('click', function () {
    var file = fileInput?.files && fileInput.files[0];
    if (!file) {
      setStatus('Choose a logo image first.');
      return;
    }
    if (toggle && !toggle.checked) {
      setStatus('Enable background removal to process this upload.');
      return;
    }
    if ((file.name || '').toLowerCase().endsWith('.svg')) {
      setStatus('SVG logos cannot use AI background removal.');
      return;
    }

    setStatus('Uploading and processing…');
    btnProcess.disabled = true;
    var fd = new FormData();
    fd.append('logo', file);

    postJson(cfg.stageUrl, fd)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        currentUuid = data.uuid;
        if (data.preview_urls && data.preview_urls.original && originalImg) {
          originalImg.src = data.preview_urls.original;
        }
        if (data.privacy_notice && privacy) {
          privacy.textContent = data.privacy_notice;
          privacy.hidden = false;
        }
        return postJson(apiUrl('/admin/settings/branding/logo-background/' + currentUuid + '/run'), {});
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        setStatus(data.status === 'completed' ? 'Processing complete. Review the result.' : (data.error_message || data.status));
        if (data.preview_urls && data.preview_urls.processed) {
          showProcessed(data.preview_urls.processed);
        }
        if (btnProcess) btnProcess.hidden = true;
        if (data.status === 'completed') {
          if (btnAccept) btnAccept.hidden = false;
          if (btnKeep) btnKeep.hidden = false;
          if (btnCancel) btnCancel.hidden = false;
        } else if (btnRetry) btnRetry.hidden = false;
      })
      .catch(function () {
        setStatus('Background removal failed. Keep the original logo or retry.');
        if (btnRetry) btnRetry.hidden = false;
      })
      .finally(function () {
        if (btnProcess) btnProcess.disabled = false;
      });
  });

  btnAccept?.addEventListener('click', function () {
    if (!currentUuid) return;
    postJson(apiUrl('/admin/settings/branding/logo-background/' + currentUuid + '/accept'), {})
      .then(function () {
        setStatus('Processed logo accepted. Reloading…');
        window.location.reload();
      });
  });

  btnKeep?.addEventListener('click', function () {
    if (!currentUuid) return;
    postJson(apiUrl('/admin/settings/branding/logo-background/' + currentUuid + '/discard'), {})
      .then(function () {
        setStatus('Keeping original upload. You can save branding normally.');
        if (fileInput) fileInput.removeAttribute('data-jp-bg-skip');
      });
  });

  btnCancel?.addEventListener('click', btnKeep?.onclick);
  btnRetry?.addEventListener('click', function () {
    if (btnProcess) btnProcess.click();
  });
})();
