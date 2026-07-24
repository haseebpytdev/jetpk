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

  // ---------------------------------------------------------------------
  // JETPK-HOMEPAGE-CMS Task 12: toggle the Save-as-Default confirmation form.
  // ---------------------------------------------------------------------
  root.querySelectorAll('[data-jp-default-toggle-form]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = root.querySelector('[data-jp-default-form]');
      if (form) form.classList.toggle('jp-is-hidden');
    });
  });

  root.querySelectorAll('[data-jp-reset-publish-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = root.querySelector('[data-jp-reset-publish-form]');
      if (form) form.classList.toggle('jp-is-hidden');
    });
  });

  // ---------------------------------------------------------------------
  // JETPK-HOMEPAGE-CMS Task 10: repeatable list add/remove.
  // Previously these buttons (data-jp-repeatable-add / -remove) existed in
  // the markup with no JS wiring anywhere in the codebase — clicking "Add
  // route" or "Add destination" did nothing at all. This clones the last
  // row in the list as a template, rewrites its name="[items][N]" and
  // id/for="...-N" attributes to a fresh index, clears its values, and
  // appends it. Not tested in a real browser in this environment — verify
  // manually before relying on it (add a row, remove a row, submit the
  // form, confirm the saved content_json looks right).
  // ---------------------------------------------------------------------
  root.querySelectorAll('[data-jp-repeatable-add]').forEach(function (addBtn) {
    var listKey = addBtn.getAttribute('data-jp-repeatable-add');
    var list = root.querySelector('.jp-repeatable-list[data-jp-repeatable="' + listKey + '"]');
    if (!list) return;

    function currentRows() {
      return list.querySelectorAll('[data-jp-repeatable-row]');
    }

    function enforceMax() {
      var max = parseInt(list.getAttribute('data-jp-repeatable-max'), 10);
      if (!max) return;
      addBtn.disabled = currentRows().length >= max;
    }

    addBtn.addEventListener('click', function () {
      var rows = currentRows();
      if (!rows.length) return;
      var template = rows[rows.length - 1];
      var oldIndex = template.getAttribute('data-index');
      var oldItemId = template.getAttribute('data-item-id');

      var nextAttr = parseInt(list.getAttribute('data-next-index'), 10);
      var newIndex = Math.max(
        isNaN(nextAttr) ? 0 : nextAttr,
        rows.length,
        (parseInt(oldIndex, 10) || 0) + 1
      );
      list.setAttribute('data-next-index', String(newIndex + 1));

      var clone = template.cloneNode(true);
      clone.setAttribute('data-index', String(newIndex));

      var bracketPattern = new RegExp('(\\[items\\]\\[)' + oldIndex + '(\\])');
      clone.querySelectorAll('[name]').forEach(function (el) {
        el.setAttribute('name', el.getAttribute('name').replace(bracketPattern, '$1' + newIndex + '$2'));
      });

      var suffixPattern = new RegExp('-' + oldIndex + '$');
      clone.querySelectorAll('[id]').forEach(function (el) {
        el.setAttribute('id', el.getAttribute('id').replace(suffixPattern, '-' + newIndex));
      });
      clone.querySelectorAll('[for]').forEach(function (el) {
        el.setAttribute('for', el.getAttribute('for').replace(suffixPattern, '-' + newIndex));
      });

      // Destinations-only: per-item file upload/remove fields are keyed by
      // item id, not by the [items][N] index, so they need their own rewrite.
      if (oldItemId) {
        var newItemId = 'new-' + newIndex;
        clone.setAttribute('data-item-id', newItemId);
        var escapedOldId = oldItemId.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
        var idBracketPattern = new RegExp('\\[' + escapedOldId + '\\]');
        clone.querySelectorAll('[name]').forEach(function (el) {
          var n = el.getAttribute('name');
          if (n.indexOf('destination_files[') === 0 || n.indexOf('destination_remove[') === 0) {
            el.setAttribute('name', n.replace(idBracketPattern, '[' + newItemId + ']'));
          }
        });
        var idInput = clone.querySelector('input[name="content[destinations][items][' + newIndex + '][id]"]');
        if (idInput) idInput.value = newItemId;
      }

      // Clear the clone's values — it should start as a blank row, not a
      // duplicate of the template it was cloned from.
      clone.querySelectorAll('input[type="text"], input[type="number"], input[type="url"], input:not([type])').forEach(function (el) {
        if (el.name.indexOf('[enabled]') === -1 && el.name.indexOf('[id]') === -1) {
          el.value = '';
        }
      });
      clone.querySelectorAll('textarea').forEach(function (el) { el.value = ''; });
      clone.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
      clone.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
        el.checked = el.name.indexOf('[enabled]') !== -1;
      });
      clone.querySelectorAll('input[type="file"]').forEach(function (el) { el.value = ''; });
      clone.querySelectorAll('img.jp-media-inline__preview').forEach(function (el) { el.remove(); });
      clone.querySelectorAll('input[name*="_remove["]').forEach(function (el) {
        (el.closest('.jp-toggle') || el).remove();
      });

      var label = clone.querySelector('.jp-muted');
      if (label) label.textContent = label.textContent.replace(/\d+/, String(rows.length + 1));

      list.appendChild(clone);
      enforceMax();
    });

    enforceMax();
  });

  root.addEventListener('click', function (event) {
    var removeBtn = event.target.closest('[data-jp-repeatable-remove]');
    if (!removeBtn) return;
    var row = removeBtn.closest('[data-jp-repeatable-row]');
    var list = row ? row.closest('.jp-repeatable-list') : null;
    if (!row) return;
    row.remove();
    if (!list) return;
    var addBtn = root.querySelector('[data-jp-repeatable-add="' + list.getAttribute('data-jp-repeatable') + '"]');
    if (!addBtn) return;
    var max = parseInt(list.getAttribute('data-jp-repeatable-max'), 10);
    addBtn.disabled = max ? list.querySelectorAll('[data-jp-repeatable-row]').length >= max : false;
  });

  // ---------------------------------------------------------------------
  // JETPK-HOMEPAGE-CMS Task 10: unsaved-changes warning. Only guards the
  // main content form (Save Draft) — clicking Publish or "Open preview tab"
  // uses separate forms and is not covered by this. Publish always acts on
  // the last-SAVED draft, not on whatever is currently typed but unsaved in
  // the browser; that's existing, unchanged behavior, not something this
  // warning tries to paper over.
  // ---------------------------------------------------------------------
  var contentForm = root.querySelector('[data-jp-content-form]');
  if (contentForm) {
    var jpFormDirty = false;
    contentForm.addEventListener('input', function () { jpFormDirty = true; });
    contentForm.addEventListener('change', function () { jpFormDirty = true; });
    contentForm.addEventListener('submit', function () {
      jpFormDirty = false;
      var holder = contentForm.querySelector('#jp-submitted-sections');
      if (!holder) return;
      holder.innerHTML = '';
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'submitted_sections[]';
      input.value = activeSection;
      holder.appendChild(input);
    });
    window.addEventListener('beforeunload', function (event) {
      if (!jpFormDirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
  }

  root.querySelectorAll('[data-jp-hero-size-control]').forEach(function (control) {
    var slider = control.querySelector('[data-jp-hero-size-slider]');
    var valueEl = control.querySelector('[data-jp-hero-size-value]');
    if (!slider || !valueEl) return;
    var sync = function () {
      valueEl.textContent = slider.value + '%';
    };
    slider.addEventListener('input', sync);
    sync();
  });
})();
