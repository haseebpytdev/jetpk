/**
 * Shared ancestor-aware visibility helpers for responsive certification audits (browser context).
 * Exported as a string for page.evaluate injection.
 */
export const VISIBILITY_AUDIT_HELPERS = `
function otaCertStyleHidden(style) {
  return style.display === "none" || style.visibility === "hidden" || style.opacity === "0";
}

function otaCertAncestorHides(el) {
  let node = el;
  while (node && node !== document.documentElement) {
    if (node.getAttribute?.("aria-hidden") === "true" || node.hasAttribute?.("inert")) return true;
    const style = window.getComputedStyle(node);
    if (otaCertStyleHidden(style)) return true;
    if (node.getAttribute?.("hidden") !== null) return true;
    const rect = node.getBoundingClientRect();
    if (rect.width < 1 && rect.height < 1 && style.overflow === "hidden") return true;
    node = node.parentElement;
  }
  return false;
}

function otaCertInClosedOverlay(el) {
  const overlay = el.closest('[role="dialog"], [role="menu"], [data-testid*="drawer"], [data-testid*="modal"]');
  if (!overlay) return false;
  const style = window.getComputedStyle(overlay);
  if (otaCertStyleHidden(style)) return true;
  if (overlay.getAttribute("aria-hidden") === "true") return true;
  const r = overlay.getBoundingClientRect();
  return r.width < 2 && r.height < 2;
}

function otaCertEffectivelyVisible(el) {
  if (!el || !el.isConnected) return false;
  if (otaCertAncestorHides(el)) return false;
  if (otaCertInClosedOverlay(el)) return false;
  const style = window.getComputedStyle(el);
  if (otaCertStyleHidden(style)) return false;
  const r = el.getBoundingClientRect();
  if (r.width < 2 && r.height < 2) return false;
  return true;
}
`;
