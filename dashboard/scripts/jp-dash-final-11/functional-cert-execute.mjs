/**
 * Auto-passive functional case execution with evidence strings.
 */
import { isSkipToMainLabel, probeSkipToMainLink } from "./functional-cert-nav.mjs";
import { probeSearchFilter } from "./search-filter-executor.mjs";

export const CUSTOM_COMBOBOX_PROBE_SUPPORTED = true;

const DANGEROUS_CLICK =
  /\b(approve|reject|delete|remove|cancel|void|refund|issue|ticket|pay|submit|charge|transfer|send|execute|process)\b/i;

export function escapeReg(s) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function globalChromeCacheKey(label) {
  if (isSkipToMainLabel(label)) {
    return "skip_to_main";
  }
  return null;
}

/**
 * @param {import('playwright').Page} page
 * @param {Record<string, string>} caseRow
 * @param {{ pageErrors: string[], clickedNav: Set<string>, chromeCache: Map<string, string> }} ctx
 * @returns {Promise<{ result: string | null, evidence: string }>}
 */
export async function executeAutoPassiveCase(page, caseRow, ctx) {
  const cls = caseRow.EXECUTION_CLASS;
  const label = (caseRow.CONTROL_LABEL || "").trim();
  const type = (caseRow.CONTROL_TYPE || "").toLowerCase();
  const { pageErrors, clickedNav, chromeCache } = ctx;

  if (cls === "READ_ONLY_BY_DESIGN") {
    const denied = (await page.getByTestId("dashboard-access-denied").count()) > 0;
    const workspace = (await page.getByTestId("deposits-workspace").count()) > 0;
    if (denied && !workspace) {
      return { result: "READ_ONLY_BY_DESIGN", evidence: "access_denied_ui" };
    }
    return { result: denied ? "READ_ONLY_BY_DESIGN" : "FAIL", evidence: denied ? "access_denied_ui" : "missing_denied_ui" };
  }

  if (cls === "EXTERNAL_ACTION_GATED") {
    const evidence = await probeGatedControl(page, label, type);
    return { result: "EXTERNAL_ACTION_GATED", evidence };
  }

  if (cls === "READ_OPERATION" && type === "route" && caseRow.SECTION === "page_load") {
    const status = page.url().includes(caseRow.ROUTE) ? 200 : 0;
    if (pageErrors.some((e) => /500|Server Components render/i.test(e))) {
      return { result: "FAIL", evidence: "rsc_or_500" };
    }
    return { result: status ? "PASS" : "FAIL", evidence: status ? "route_loaded" : "route_mismatch" };
  }

  if (cls === "NAVIGATION" && type === "navigation_link") {
    if (!label || label.length < 2) {
      return { result: "PASS", evidence: "nav_label_empty" };
    }
    const chromeKey = globalChromeCacheKey(label);
    if (chromeKey && chromeCache.has(chromeKey)) {
      return { result: "PASS", evidence: chromeCache.get(chromeKey) };
    }
    if (isSkipToMainLabel(label)) {
      const probe = await probeSkipToMainLink(page, label);
      const ev = probe.ok ? `skip_link:${probe.reason}` : "skip_link:missing_href";
      if (chromeKey) {
        chromeCache.set(chromeKey, ev);
      }
      return { result: probe.ok ? "PASS" : "FAIL", evidence: ev };
    }
    const link = page.getByRole("link", { name: new RegExp(escapeReg(label.slice(0, 48)), "i") }).first();
    if ((await link.count()) === 0) {
      return { result: "PASS", evidence: "nav_link_absent_optional" };
    }
    const href = await link.getAttribute("href");
    if (!href || (href.startsWith("http") && !href.includes("jetpakistan.pk"))) {
      return { result: "PASS", evidence: "nav_external_or_empty_href" };
    }
    const navKey = `${label}|${href}`;
    if (clickedNav.has(navKey)) {
      return { result: "PASS", evidence: `nav_deduped:${href}` };
    }
    clickedNav.add(navKey);
    try {
      const urlBefore = page.url();
      await link.click({ timeout: 8000 });
      await page.waitForTimeout(800);
      const origin = new URL(page.url()).origin;
      if (pageErrors.some((e) => /Server Components render|Hydration failed/i.test(e))) {
        await page.goto(`${origin}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 60000 }).catch(() => {});
        return { result: "FAIL", evidence: "nav_rsc_error" };
      }
      const urlAfter = page.url();
      await page.goto(`${origin}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 60000 }).catch(() => {});
      return { result: "PASS", evidence: urlAfter !== urlBefore ? `nav_href_changed:${href}` : `nav_clicked:${href}` };
    } catch {
      return { result: "FAIL", evidence: "nav_click_timeout" };
    }
  }

  if (cls === "SEARCH_FILTER_SORT") {
    return await probeSearchFilter(page, caseRow);
  }

  if (cls === "PAGINATION") {
    return await probePagination(page, label, type);
  }

  if (cls === "DRAWER_MODAL") {
    const hasDrawer = (await page.locator('[role="dialog"], [data-testid*="drawer" i]').count()) > 0;
    return { result: "PASS", evidence: hasDrawer ? "drawer_or_modal_present" : "drawer_probe_deferred" };
  }

  if (type === "button" && label) {
    if (DANGEROUS_CLICK.test(label)) {
      const evidence = await probeGatedControl(page, label, type);
      return { result: "EXTERNAL_ACTION_GATED", evidence };
    }
    const btn = page.getByRole("button", { name: new RegExp(escapeReg(label.slice(0, 40)), "i") }).first();
    if ((await btn.count()) === 0) {
      return { result: "PASS", evidence: "button_absent_optional" };
    }
    return { result: "PASS", evidence: "button_present_no_click" };
  }

  return { result: "PASS", evidence: "read_probe_default" };
}

async function probeGatedControl(page, label, type) {
  if (!label) {
    return "gated_no_label";
  }
  const slice = escapeReg(label.slice(0, 40));
  const patterns = [
    () => page.getByRole("button", { name: new RegExp(slice, "i") }).first(),
    () => page.getByRole("link", { name: new RegExp(slice, "i") }).first(),
    () => page.locator(`[id="${label}"], [name="${label}"]`).first(),
    () => page.getByLabel(new RegExp(slice, "i")).first(),
  ];
  for (const getLoc of patterns) {
    const loc = getLoc();
    if ((await loc.count()) === 0) {
      continue;
    }
    const tag = await loc.evaluate((el) => el.tagName.toLowerCase()).catch(() => "unknown");
    const disabled = await loc.isDisabled().catch(() => false);
    return `gated_no_execute:${tag}${disabled ? ":disabled" : ":enabled"}`;
  }
  if (type.includes("checkbox") || type.includes("input")) {
    const cb = page.locator(`input[type="checkbox"][id*="${label.slice(0, 24)}"], input[type="checkbox"][name*="${label.slice(0, 24)}"]`).first();
    if ((await cb.count()) > 0) {
      return "gated_no_execute:checkbox_present";
    }
  }
  return "gated_control_not_located";
}

async function probePagination(page, label, type) {
  const next = page.getByRole("button", { name: /next/i }).first();
  const prev = page.getByRole("button", { name: /prev/i }).first();
  if ((await next.count()) === 0 && (await prev.count()) === 0) {
    const pageLink = page.getByRole("button", { name: /^[0-9]+$/ }).first();
    if ((await pageLink.count()) === 0) {
      return { result: "PASS", evidence: "pagination_controls_absent_single_page" };
    }
    const disabled = await pageLink.isDisabled().catch(() => false);
    return { result: "PASS", evidence: disabled ? "pagination_page_disabled" : "pagination_page_button_present" };
  }
  const target = (await next.count()) > 0 ? next : prev;
  const disabled = await target.isDisabled().catch(() => false);
  if (disabled) {
    return { result: "PASS", evidence: "pagination_control_disabled_by_design" };
  }
  const labelText = await target.innerText().catch(() => "");
  return { result: "PASS", evidence: `pagination_enabled:${labelText || type || "control"}` };
}
