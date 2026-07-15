/**
 * OTA Dashboard Foundation JS — Phase 1 (shared authenticated shell)
 * JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4
 *
 * Registers the Alpine data component used by <x-dashboard.shell>:
 *   - drawerOpen state for the off-canvas mobile sidebar (Customer / Agent);
 *   - body scroll lock while the drawer is open;
 *   - focus management (move focus into the drawer on open, restore on close,
 *     simple Tab focus trap, Escape to close — Escape is also bound in the shell
 *     markup via @keydown.escape.window as a fallback).
 *
 * The role-aware profile menu uses the EXISTING account-dropdown behaviour and is not
 * managed here. No other global behaviour is changed. Alpine.js is already loaded by
 * the app; this file only adds a data component on alpine:init.
 *
 * CACHE-BUST: linked via ui_asset(); bump ?v= on edit (see PHASE1-ASSET-VERSION-MANIFEST.md).
 */
(function () {
  'use strict';

  function register(Alpine) {
    Alpine.data('otaDashboardShell', function () {
      return {
        drawerOpen: false,
        _lastFocused: null,
        _keydownHandler: null,

        openDrawer: function () {
          if (this.drawerOpen) return;
          this._lastFocused = document.activeElement;
          this.drawerOpen = true;
          document.body.classList.add('ota-dashboard-drawer-open');

          var self = this;
          this.$nextTick(function () {
            var drawer = self.$refs.drawer;
            if (!drawer) return;
            var focusable = drawer.querySelector(
              'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            (focusable || drawer).focus({ preventScroll: true });
            self._bindTrap(drawer);
          });
        },

        closeDrawer: function () {
          if (!this.drawerOpen) return;
          this.drawerOpen = false;
          document.body.classList.remove('ota-dashboard-drawer-open');
          this._unbindTrap();
          if (this._lastFocused && typeof this._lastFocused.focus === 'function') {
            this._lastFocused.focus({ preventScroll: true });
          }
        },

        _bindTrap: function (drawer) {
          this._unbindTrap();
          this._keydownHandler = function (e) {
            if (e.key !== 'Tab') return;
            var nodes = drawer.querySelectorAll(
              'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (!nodes.length) return;
            var first = nodes[0];
            var last = nodes[nodes.length - 1];
            if (e.shiftKey && document.activeElement === first) {
              e.preventDefault();
              last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
              e.preventDefault();
              first.focus();
            }
          };
          drawer.addEventListener('keydown', this._keydownHandler);
          this._trapTarget = drawer;
        },

        _unbindTrap: function () {
          if (this._trapTarget && this._keydownHandler) {
            this._trapTarget.removeEventListener('keydown', this._keydownHandler);
          }
          this._keydownHandler = null;
          this._trapTarget = null;
        },
      };
    });
  }

  if (window.Alpine && typeof window.Alpine.data === 'function') {
    register(window.Alpine);
  } else {
    document.addEventListener('alpine:init', function () {
      if (window.Alpine) register(window.Alpine);
    });
  }
})();
