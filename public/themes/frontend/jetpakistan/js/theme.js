// JetPakistan — theme toggle, sticky header, mobile drawer, SSR-safe loader
(function () {
  var loader = document.getElementById('jpLoader');
  if (loader) {
    var hidden = loader.classList.contains('done');
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ssrLoader = loader.getAttribute('data-jp-loader') === 'ssr';

    function hideLoader() {
      if (hidden) return;
      hidden = true;
      loader.classList.remove('jp-loader--active');
      loader.classList.add('done');
    }

    if (reduced || ssrLoader) {
      hideLoader();
    } else {
      loader.classList.add('jp-loader--active');
      loader.classList.remove('done');
      requestAnimationFrame(function () {
        requestAnimationFrame(hideLoader);
      });
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideLoader, { once: true });
      } else {
        hideLoader();
      }
      window.addEventListener('load', hideLoader, { once: true });
      window.setTimeout(hideLoader, 450);
    }
  }

  var root = document.documentElement;
  var btn = document.getElementById('themeToggle');
  if (btn) btn.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'night' ? 'day' : 'night';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('jp-theme', next); } catch (e) {}
  });

  var header = document.getElementById('header');
  if (header) {
    var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 20); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  var drawer = document.getElementById('drawer');
  var open = document.getElementById('openDrawer');
  if (drawer) {
    if (open) open.addEventListener('click', function () { drawer.classList.add('open'); });
    drawer.querySelectorAll('[data-close]').forEach(function (el) {
      el.addEventListener('click', function () { drawer.classList.remove('open'); });
    });
    drawer.querySelectorAll('.panel a').forEach(function (a) {
      a.addEventListener('click', function () { drawer.classList.remove('open'); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') drawer.classList.remove('open');
    });
  }

  var registerMenu = document.querySelector('[data-jp-register-menu]');
  if (registerMenu) {
    var regTrigger = registerMenu.querySelector('.jp-register-menu__trigger');
    var regPanel = registerMenu.querySelector('.jp-register-menu__panel');
    if (regTrigger && regPanel) {
      regTrigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = registerMenu.classList.toggle('is-open');
        regPanel.hidden = !isOpen;
        regTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
      document.addEventListener('click', function (e) {
        if (!registerMenu.contains(e.target)) {
          registerMenu.classList.remove('is-open');
          regPanel.hidden = true;
          regTrigger.setAttribute('aria-expanded', 'false');
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          registerMenu.classList.remove('is-open');
          regPanel.hidden = true;
          regTrigger.setAttribute('aria-expanded', 'false');
        }
      });
    }
  }

  document.querySelectorAll('[data-account-menu]').forEach(function (menu) {
    var trigger = menu.querySelector('[data-account-trigger]');
    var dropdown = menu.querySelector('[data-account-dropdown]');
    if (!trigger || !dropdown) return;

    function setOpen(open) {
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      dropdown.hidden = !open;
    }

    trigger.addEventListener('click', function (event) {
      event.stopPropagation();
      var open = trigger.getAttribute('aria-expanded') === 'true';
      document.querySelectorAll('[data-account-menu]').forEach(function (other) {
        if (other === menu) return;
        var otherTrigger = other.querySelector('[data-account-trigger]');
        var otherDropdown = other.querySelector('[data-account-dropdown]');
        if (!otherTrigger || !otherDropdown) return;
        otherTrigger.setAttribute('aria-expanded', 'false');
        otherDropdown.hidden = true;
      });
      setOpen(!open);
    });

    document.addEventListener('click', function (event) {
      if (!menu.contains(event.target)) {
        setOpen(false);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    });
  });
})();
