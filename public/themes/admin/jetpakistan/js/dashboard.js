// JetPakistan — dashboard shell interactions
(function () {
  'use strict';

  function initSidebar() {
    try {
      if (localStorage.getItem('jp-dash-side') === 'collapsed') {
        document.body.classList.add('side-collapsed');
      }
    } catch (e) {}

    document.querySelectorAll('[data-jp-side-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var collapsed = document.body.classList.toggle('side-collapsed');
        try {
          localStorage.setItem('jp-dash-side', collapsed ? 'collapsed' : 'expanded');
        } catch (e) {}
      });
    });

    document.querySelectorAll('[data-jp-side-open]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        document.body.classList.toggle('side-open');
      });
    });

    document.addEventListener('click', function (e) {
      if (document.body.classList.contains('side-open')
        && !e.target.closest('.jp-side2, [data-jp-side-open]')) {
        document.body.classList.remove('side-open');
      }
    });
  }

  function initTheme() {
    var store = 'jp-theme';
    var root = document.documentElement;
    function read() {
      try {
        var s = localStorage.getItem(store);
        if (s === 'day' || s === 'night') return s;
      } catch (e) {}
      return 'day';
    }
    function apply(mode) {
      root.setAttribute('data-theme', mode);
      try { localStorage.setItem(store, mode); } catch (e) {}
    }
    apply(read());
    document.querySelectorAll('[data-jp-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        apply(root.getAttribute('data-theme') === 'day' ? 'night' : 'day');
      });
    });
  }

  function initProfileMenu() {
    document.querySelectorAll('[data-jp-profile-wrap]').forEach(function (wrap) {
      var toggle = wrap.querySelector('[data-jp-profile-toggle]');
      var menu = wrap.querySelector('[data-jp-profile-menu]');
      if (!toggle || !menu) return;

      toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = !menu.hasAttribute('hidden');
        if (open) {
          menu.setAttribute('hidden', '');
          toggle.setAttribute('aria-expanded', 'false');
        } else {
          menu.removeAttribute('hidden');
          toggle.setAttribute('aria-expanded', 'true');
        }
      });
    });

    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-jp-profile-wrap]')) return;
      document.querySelectorAll('[data-jp-profile-menu]').forEach(function (menu) {
        menu.setAttribute('hidden', '');
      });
      document.querySelectorAll('[data-jp-profile-toggle]').forEach(function (btn) {
        btn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  function initModalShim() {
    function openModal(el) {
      if (!el) return;
      el.classList.add('is-open');
      el.setAttribute('aria-hidden', 'false');
    }
    function closeModal(el) {
      if (!el) return;
      el.classList.remove('is-open');
      el.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var target = btn.getAttribute('data-bs-target');
        if (!target) return;
        openModal(document.querySelector(target));
      });
    });

    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var modal = btn.closest('.modal');
        closeModal(modal);
      });
    });

    document.querySelectorAll('.modal').forEach(function (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal(modal);
      });
    });

    document.querySelectorAll('[data-bs-dismiss="alert"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var alert = btn.closest('.alert');
        if (alert) alert.remove();
      });
    });
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  ready(function () {
    initSidebar();
    initTheme();
    initProfileMenu();
    initModalShim();
  });
})();
