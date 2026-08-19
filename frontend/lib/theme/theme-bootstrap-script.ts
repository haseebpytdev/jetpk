import { THEME_STORAGE_KEY } from "./constants";

/**
 * Inline bootstrap executed before paint to prevent theme flash.
 * Must remain tiny and CSP-compatible (no external deps).
 * Visual audits may pass jpThemePref on the query string for deterministic theme without stacked init scripts.
 */
export const themeBootstrapScript = `(function(){try{var k=${JSON.stringify(THEME_STORAGE_KEY)};var params=new URLSearchParams(window.location.search);if(params.get("jpAuditReset")==="1"){localStorage.clear();sessionStorage.clear();}var qp=params.get("jpThemePref");if(qp==="light"||qp==="dark"||qp==="system"){localStorage.setItem(k,qp==="system"?"light":qp);}var s=localStorage.getItem(k);var p=s==="dark"?"dark":"light";document.documentElement.setAttribute("data-theme",p);document.documentElement.style.colorScheme=p;}catch(e){document.documentElement.setAttribute("data-theme","light");document.documentElement.style.colorScheme="light";}})();`;
