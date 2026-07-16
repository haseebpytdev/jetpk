/**
 * JetPK page settings editor — split workspace, section nav, media file controls, preview.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-jp-page-editor]');
  if (!root) return;

  var tabs = root.querySelectorAll('[data-jp-editor-tab]');
  var panels = root.querySelectorAll('[data-jp-editor-panel]');

  function activateEditorTab(key) {
    tabs.forEach(function (t) {
      var active = t.getAttribute('data-jp-editor-tab') === key;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach(function (panel) {
      panel.classList.toggle('jp-is-hidden', panel.getAttribute('data-jp-editor-panel') !== key);
    });
  }

  if (window.location.hash === '#media') {
    activateEditorTab('media');
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      activateEditorTab(tab.getAttribute('data-jp-editor-tab'));
    });
  });

  var sectionNav = root.querySelector('[data-jp-section-nav]');
  var sectionPanels = root.querySelectorAll('[data-jp-section-panel]');
  var activeSection = 'hero';

  if (sectionNav && sectionPanels.length) {
    sectionNav.querySelectorAll('[data-jp-section]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-jp-section');
        activeSection = key || activeSection;
        sectionNav.querySelectorAll('[data-jp-section]').forEach(function (t) {
          t.classList.toggle('is-active', t === btn);
        });
        sectionPanels.forEach(function (panel) {
          panel.classList.toggle('jp-is-hidden', panel.getAttribute('data-jp-section-panel') !== key);
        });
      });
    });
  }

  var contentForm = root.querySelector('[data-jp-content-form]');
  if (contentForm && sectionNav) {
    contentForm.addEventListener('submit', function () {
      var holder = contentForm.querySelector('#jp-submitted-sections');
      if (!holder) return;
      holder.innerHTML = '';
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'submitted_sections[]';
      input.value = activeSection;
      holder.appendChild(input);
    });
  }

  var mediaTabs = root.querySelectorAll('[data-jp-media-section]');
  var mediaPanels = root.querySelectorAll('[data-jp-media-panel]');
  mediaTabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var section = btn.getAttribute('data-jp-media-section');
      mediaTabs.forEach(function (t) { t.classList.toggle('is-active', t === btn); });
      mediaPanels.forEach(function (panel) {
        panel.classList.toggle('jp-is-hidden', panel.getAttribute('data-jp-media-panel') !== section);
      });
    });
  });

  var frame = root.querySelector('[data-jp-preview-frame]');
  var frameWrap = root.querySelector('[data-jp-preview-frame-wrap]');
  var loading = root.querySelector('[data-jp-preview-loading]');
  var previewUrl = root.getAttribute('data-preview-url') || (frame ? frame.getAttribute('src') : '');

  function refreshPreview() {
    if (!frame || !previewUrl) return;
    if (loading) loading.classList.remove('jp-is-hidden');
    var sep = previewUrl.indexOf('?') >= 0 ? '&' : '?';
    frame.src = previewUrl + sep + '_jp_preview=' + Date.now();
    frame.addEventListener('load', function onLoad() {
      if (loading) loading.classList.add('jp-is-hidden');
      frame.removeEventListener('load', onLoad);
    });
  }

  var refreshBtn = root.querySelector('[data-jp-preview-refresh]');
  if (refreshBtn) refreshBtn.addEventListener('click', refreshPreview);

  if (document.querySelector('[data-jp-flash-status]') && window.location.hash === '#media') {
    refreshPreview();
  }

  root.querySelectorAll('[data-jp-preview-devices] [data-width]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      root.querySelectorAll('[data-jp-preview-devices] .jp-queue-tab').forEach(function (b) {
        b.classList.toggle('is-active', b === btn);
      });
      if (frameWrap) {
        frameWrap.style.setProperty('--jp-preview-w', btn.getAttribute('data-width'));
        frameWrap.setAttribute('data-preview-mode', btn.getAttribute('data-preview-mode') || 'desktop');
      }
    });
  });

  root.querySelectorAll('[data-jp-file-input]').forEach(function (input) {
    var nameEl = input.closest('.jp-file-control');
  var label = nameEl ? nameEl.querySelector('[data-jp-file-name]') : null;
    input.addEventListener('change', function () {
      if (label) {
        label.textContent = input.files && input.files[0] ? input.files[0].name : 'No file chosen';
      }
    });
  });
})();
