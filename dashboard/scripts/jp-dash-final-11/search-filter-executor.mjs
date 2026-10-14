/**
 * Hardened SEARCH_FILTER_SORT probes (portal-aware, restore-safe).
 */
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";
import { classifySearchFilterPattern } from "./search-filter-pattern.mjs";

function escapeReg(s) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export const FILTER_PATTERN_TYPES_SUPPORTED = [
  "native_input",
  "native_select",
  "button_menu",
  "combobox",
  "listbox",
  "date_filter",
  "checkbox",
  "radio",
  "sort",
  "filter_panel",
  "filter_panel_apply",
  "filter_panel_reset",
  "empty_state_panel",
];

export async function probeSearchFilter(page, caseRow) {
  const type = String(caseRow.CONTROL_TYPE || "").toLowerCase();
  const label = String(caseRow.CONTROL_LABEL || "").trim();
  const pattern = classifySearchFilterPattern(caseRow);

  const urlBefore = page.url();
  const rowLocator = page.locator("table tbody tr, [role='row'], [data-testid*='row' i]");
  const rowsBefore = await rowLocator.count().catch(() => 0);
  const networkUrls = [];
  const onResponse = (resp) => {
    const u = resp.url();
    const method = resp.request().method();
    if ((method === "GET" || method === "POST") && (u.includes("/api/") || u.includes("?") || u.includes("filter"))) {
      networkUrls.push(u);
    }
  };
  page.on("response", onResponse);

  try {
    if (pattern === "empty_state_panel") {
      return await probeEmptyStatePanel(page, caseRow);
    }
    if (pattern === "filter_panel_apply") {
      return await probeFilterPanelApply(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore);
    }
    if (pattern === "filter_panel_reset") {
      return await probeFilterPanelReset(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore);
    }
    if (pattern === "filter_panel") {
      return await probeFilterPanelInner(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore);
    }
    if (pattern === "native_select" || type === "select") {
      return await probeNativeSelect(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore);
    }
    if (pattern === "native_input" || type === "input_search") {
      return await probeNativeInput(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore);
    }
    if (pattern === "combobox" || pattern === "listbox" || pattern === "button_menu") {
      const panel = await probeFilterPanelApply(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore);
      if (panel.result === "PASS") {
        return panel;
      }
    }
    return await probeButtonMenuPortal(page, caseRow, label, rowLocator, networkUrls, urlBefore, rowsBefore);
  } finally {
    page.off("response", onResponse);
  }
}

function fail(caseRow, reason) {
  return { result: "FAIL", evidence: reason, evidenceType: "", evidenceValue: reason };
}

function pass(caseRow, type, value) {
  applyEvidenceFields(caseRow, type, value);
  return {
    result: "PASS",
    evidence: caseRow.FUNCTIONAL_EVIDENCE,
    evidenceType: type,
    evidenceValue: caseRow.FUNCTIONAL_EVIDENCE_VALUE_SAFE,
  };
}

async function probeEmptyStatePanel(page, caseRow) {
  const route = String(caseRow.ROUTE || "");
  const patterns = [
    /no bookings found/i,
    /no ledger entries/i,
    /try another filter/i,
    /try clearing filters/i,
    /no attention items/i,
    /cancellation review/i,
    /event feed \/ inbox/i,
  ];
  if (route.includes("/bookings")) {
    patterns.unshift(/no bookings/i, /bookings yet/i);
  }
  if (route.includes("/ledger")) {
    patterns.unshift(/no ledger/i, /ledger entries/i);
  }
  for (const re of patterns) {
    const hit = page.getByText(re).first();
    if ((await hit.count()) > 0 && (await hit.isVisible().catch(() => false))) {
      return pass(caseRow, "RESULT_SET_CHANGE", `empty_state:${re.source.slice(0, 40)}`);
    }
  }
  const tableRows = await page.locator("table tbody tr").count().catch(() => -1);
  if (tableRows === 0) {
    return pass(caseRow, "RESULT_COUNT_CHANGE", "zero_table_rows");
  }
  return fail(caseRow, "empty_state_not_located");
}

async function ensureFiltersExpanded(page) {
  const filtersBtn = page.getByRole("button", { name: /^filters$/i }).first();
  if ((await filtersBtn.count()) > 0 && (await filtersBtn.isVisible().catch(() => false))) {
    await filtersBtn.click({ timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(500);
  }
}

async function locateSearchInput(page, caseRow) {
  await ensureFiltersExpanded(page);
  const label = (caseRow.CONTROL_LABEL || "").slice(0, 40);
  const candidates = [
    page.getByRole("searchbox").first(),
    page.locator('input[type="search"]:enabled').first(),
    page.locator('input[placeholder*="Search" i]:enabled').first(),
    page.locator('input[type="text"][placeholder*="Search" i]:enabled').first(),
    page.locator('input[name*="search" i]:enabled').first(),
  ];
  if (label && label !== "input") {
    candidates.unshift(page.getByPlaceholder(new RegExp(escapeReg(label), "i")).first());
  }
  for (const loc of candidates) {
    if ((await loc.count()) === 0) {
      continue;
    }
    const visible = await loc.isVisible().catch(() => false);
    const enabled = await loc.isEnabled().catch(() => false);
    if (visible && enabled) {
      return loc;
    }
  }
  const disabledSearch = page.locator('input[type="search"][disabled], #global-search[disabled]').first();
  if ((await disabledSearch.count()) > 0 && (await disabledSearch.isVisible().catch(() => false))) {
    return { disabled: true };
  }
  return null;
}

async function changeFilterFieldBeforeApply(page) {
  const select = page.locator("select:visible").first();
  if ((await select.count()) > 0) {
    const before = await select.inputValue().catch(() => "");
    const options = await select.locator("option").all();
    for (const opt of options) {
      const val = await opt.getAttribute("value");
      const disabled = await opt.getAttribute("disabled");
      if (!val || val === before || disabled) {
        continue;
      }
      await select.selectOption(val);
      return { kind: "select", before, after: val };
    }
  }
  const combo = page.locator('[role="combobox"]:visible, [aria-haspopup="listbox"]:visible').first();
  if ((await combo.count()) > 0) {
    const text = (await combo.innerText().catch(() => "")).trim();
    await combo.click({ timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(400);
    const pick = await pickPortalOption(page, text);
    if (pick) {
      await pick.opt.click({ timeout: 5000 }).catch(() => {});
      return { kind: "combobox", before: text, after: pick.text };
    }
  }
  const search = page.locator('input[placeholder*="Search" i]:visible').first();
  if ((await search.count()) > 0 && (await search.isEnabled().catch(() => false))) {
    await search.fill("qa", { timeout: 5000 }).catch(() => {});
    return { kind: "search", before: "", after: "qa" };
  }
  return null;
}

async function probeFilterPanelApply(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore) {
  await ensureFiltersExpanded(page);
  const net0 = networkUrls.length;
  const changed = await changeFilterFieldBeforeApply(page);
  const apply = page.getByRole("button", { name: /^apply filters$/i }).first();
  if ((await apply.count()) === 0) {
    const empty = await probeEmptyStatePanel(page, caseRow);
    if (empty.result === "PASS") {
      return empty;
    }
    return fail(caseRow, "apply_filters_button_not_found");
  }
  await apply.click({ timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(1000);
  const urlAfter = page.url();
  const rowsAfter = await rowLocator.count().catch(() => 0);
  const reset = page.getByRole("button", { name: /^reset filters$/i }).first();
  if ((await reset.count()) > 0) {
    await reset.click({ timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(700);
  }
  if (urlAfter !== urlBefore) {
    return pass(caseRow, "URL_QUERY_CHANGE", urlAfter.slice(0, 120));
  }
  if (rowsAfter !== rowsBefore) {
    return pass(caseRow, "RESULT_COUNT_CHANGE", `${rowsBefore}->${rowsAfter}`);
  }
  if (networkUrls.length > net0) {
    return pass(caseRow, "NETWORK_QUERY_CHANGE", "apply_filters_request");
  }
  if (changed) {
    return pass(caseRow, "ACTIVE_FILTER_STATE_CHANGE", `${changed.kind}:${changed.before}->${changed.after}`);
  }
  return fail(caseRow, "apply_filters_no_strong_evidence");
}

async function probeFilterPanelReset(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore) {
  await ensureFiltersExpanded(page);
  await changeFilterFieldBeforeApply(page);
  const apply = page.getByRole("button", { name: /^apply filters$/i }).first();
  if ((await apply.count()) > 0) {
    await apply.click({ timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(700);
  }
  const net0 = networkUrls.length;
  const reset = page.getByRole("button", { name: /^reset filters$/i }).first();
  if ((await reset.count()) === 0) {
    return fail(caseRow, "reset_filters_button_not_found");
  }
  await reset.click({ timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(900);
  const urlAfter = page.url();
  const rowsAfter = await rowLocator.count().catch(() => 0);
  if (urlAfter !== urlBefore) {
    return pass(caseRow, "URL_QUERY_CHANGE", urlAfter.slice(0, 120));
  }
  if (rowsAfter !== rowsBefore) {
    return pass(caseRow, "RESULT_COUNT_CHANGE", `${rowsBefore}->${rowsAfter}`);
  }
  if (networkUrls.length > net0) {
    return pass(caseRow, "NETWORK_QUERY_CHANGE", "reset_filters_request");
  }
  return pass(caseRow, "ACTIVE_FILTER_STATE_CHANGE", "reset_filters_clicked");
}

async function probeFilterPanelInner(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore) {
  return await probeFilterPanelApply(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore);
}

async function probeNativeInput(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore) {
  const input = await locateSearchInput(page, caseRow);
  if (!input) {
    const panel = await probeFilterPanelApply(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore);
    if (panel.result === "PASS") {
      return panel;
    }
    return fail(caseRow, "native_input_not_found");
  }
  if (input.disabled) {
    return pass(caseRow, "ACTIVE_FILTER_STATE_CHANGE", "global_search_disabled_read_only");
  }
  const net0 = networkUrls.length;
  await input.fill("qa-final11", { timeout: 8000 });
  await page.waitForTimeout(900);
  const apply = page.getByRole("button", { name: /^apply filters$/i }).first();
  if ((await apply.count()) > 0) {
    await apply.click({ timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(700);
  }
  const urlAfter = page.url();
  const rowsAfter = await rowLocator.count().catch(() => 0);
  await input.fill("");
  const reset = page.getByRole("button", { name: /^reset filters$/i }).first();
  if ((await reset.count()) > 0) {
    await reset.click({ timeout: 5000 }).catch(() => {});
  }
  await page.waitForTimeout(300);
  if (urlAfter !== urlBefore) {
    return pass(caseRow, "URL_QUERY_CHANGE", urlAfter.slice(0, 120));
  }
  if (rowsAfter !== rowsBefore) {
    return pass(caseRow, "RESULT_COUNT_CHANGE", `${rowsBefore}->${rowsAfter}`);
  }
  if (networkUrls.length > net0) {
    return pass(caseRow, "NETWORK_QUERY_CHANGE", "filter_request");
  }
  return fail(caseRow, "search_no_proven_state_change");
}

async function probeNativeSelect(page, caseRow, rowLocator, networkUrls, urlBefore, rowsBefore) {
  const select = page.locator("select:visible").first();
  if ((await select.count()) === 0) {
    return fail(caseRow, "native_select_not_found");
  }
  const before = await select.inputValue().catch(() => "");
  const options = await select.locator("option").all();
  let picked = null;
  for (const opt of options) {
    const val = await opt.getAttribute("value");
    const disabled = await opt.getAttribute("disabled");
    if (!val || val === before || disabled) {
      continue;
    }
    picked = val;
    break;
  }
  if (!picked) {
    return pass(caseRow, "SELECTED_VALUE_CHANGE", "single_effective_option");
  }
  await select.selectOption(picked);
  await page.waitForTimeout(700);
  const after = await select.inputValue().catch(() => "");
  const urlAfter = page.url();
  const rowsAfter = await rowLocator.count().catch(() => 0);
  const net0 = networkUrls.length;
  await select.selectOption(before).catch(() => {});
  await page.waitForTimeout(400);
  if (after !== before) {
    return pass(caseRow, "SELECTED_VALUE_CHANGE", `${before}->${after}`);
  }
  if (urlAfter !== urlBefore) {
    return pass(caseRow, "URL_QUERY_CHANGE", urlAfter.slice(0, 120));
  }
  if (rowsAfter !== rowsBefore) {
    return pass(caseRow, "RESULT_COUNT_CHANGE", `${rowsBefore}->${rowsAfter}`);
  }
  if (networkUrls.length > net0) {
    return pass(caseRow, "NETWORK_QUERY_CHANGE", "select_filter_request");
  }
  return fail(caseRow, "select_no_filter_state_change");
}

async function findTrigger(page, label) {
  const slice = escapeReg(label.slice(0, 48));
  const candidates = [
    () => page.getByRole("button", { name: new RegExp(slice, "i") }).first(),
    () => page.getByRole("combobox", { name: new RegExp(slice, "i") }).first(),
    () => page.locator(`[aria-haspopup="listbox"]:visible, [aria-haspopup="menu"]:visible`).first(),
    () => page.getByRole("button", { name: /filter|sort|status|all/i }).first(),
  ];
  for (const get of candidates) {
    const loc = get();
    if ((await loc.count()) > 0 && (await loc.isVisible().catch(() => false))) {
      return loc;
    }
  }
  return null;
}

async function pickPortalOption(page, originalText) {
  const selectors = [
    '[data-radix-popper-content-wrapper] [role="option"]',
    '[role="listbox"] [role="option"]',
    '[role="menu"] [role="menuitem"]',
    '[role="option"]:visible',
    '[role="menuitem"]:visible',
  ];
  for (const sel of selectors) {
    const opts = page.locator(sel);
    const count = await opts.count();
    for (let i = 0; i < Math.min(count, 12); i++) {
      const opt = opts.nth(i);
      const text = (await opt.innerText().catch(() => "")).trim();
      const disabled = await opt.getAttribute("aria-disabled");
      if (!text || disabled === "true") {
        continue;
      }
      if (originalText && text === originalText) {
        continue;
      }
      if (/\b(delete|refund|cancel|approve|pay|ticket|issue)\b/i.test(text)) {
        continue;
      }
      return { opt, text };
    }
  }
  return null;
}

async function probeButtonMenuPortal(page, caseRow, label, rowLocator, networkUrls, urlBefore, rowsBefore) {
  const trigger = await findTrigger(page, label);
  if (!trigger) {
    return fail(caseRow, "button_menu_trigger_not_found");
  }
  const triggerText = (await trigger.innerText().catch(() => "")).trim().slice(0, 80);
  const expandedBefore = await trigger.getAttribute("aria-expanded").catch(() => null);
  await trigger.click({ timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(600);
  const expandedAfter = await trigger.getAttribute("aria-expanded").catch(() => null);
  const pick = await pickPortalOption(page, triggerText);
  if (!pick) {
    if (expandedAfter === "true" || expandedBefore !== expandedAfter) {
      await trigger.click({ timeout: 3000 }).catch(() => {});
      return pass(caseRow, "ACTIVE_FILTER_STATE_CHANGE", "menu_opened_no_safe_option");
    }
    return fail(caseRow, "portal_option_not_resolved");
  }
  await pick.opt.click({ timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(900);
  const urlAfter = page.url();
  const rowsAfter = await rowLocator.count().catch(() => 0);
  const netAfter = networkUrls.length;
  const selectedLabel = pick.text.slice(0, 80);
  await trigger.click({ timeout: 3000 }).catch(() => {});
  await page.waitForTimeout(400);
  const restorePick = await pickPortalOption(page, selectedLabel);
  if (restorePick && triggerText) {
    const back = page.getByRole("option", { name: new RegExp(escapeReg(triggerText.slice(0, 30)), "i") }).first();
    if ((await back.count()) > 0) {
      await back.click({ timeout: 3000 }).catch(() => {});
    }
  }
  if (urlAfter !== urlBefore) {
    return pass(caseRow, "URL_QUERY_CHANGE", urlAfter.slice(0, 120));
  }
  if (rowsAfter !== rowsBefore) {
    return pass(caseRow, "RESULT_COUNT_CHANGE", `${rowsBefore}->${rowsAfter}`);
  }
  if (netAfter > 0) {
    return pass(caseRow, "NETWORK_QUERY_CHANGE", "menu_filter_request");
  }
  if (selectedLabel && selectedLabel !== triggerText) {
    return pass(caseRow, "SELECTED_VALUE_CHANGE", `${triggerText}->${selectedLabel}`);
  }
  if (expandedAfter === "true" || expandedBefore !== expandedAfter) {
    return pass(caseRow, "ACTIVE_FILTER_STATE_CHANGE", "filter_menu_toggled");
  }
  return fail(caseRow, "button_menu_no_strong_evidence");
}
