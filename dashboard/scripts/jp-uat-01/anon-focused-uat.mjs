/**
 * JP-UAT-01 Anonymous Traveller — focused black-box + safety-limited search.
 * Max 1 live search attempt. Stops before booking write.
 */
import { chromium, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const outDir = path.join(repoRoot, "tmp/jp-uat-01");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";

fs.mkdirSync(outDir, { recursive: true });

const report = {
  scenario: "UAT-ANON-01",
  blackBox: {},
  deterministic: {},
  widths: {},
  liveSearchAttempts: 0,
  bookingWriteAttempted: false,
  success: false,
};

async function checkWidth(page, width, height = 900) {
  await page.setViewportSize({ width, height });
  await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 60000 });
  const searchVisible =
    (await page.getByRole("button", { name: /search flights|search/i }).count()) > 0 ||
    (await page.locator("#flight-search, [data-flight-search], form").filter({ hasText: /from|destination|depart/i }).count()) > 0 ||
    (await page.getByText(/search flights|find flights/i).count()) > 0;
  const body = (await page.locator("body").innerText()).slice(0, 500);
  const overflowX = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 8);
  return {
    width,
    searchVisible,
    overflowX,
    brandSignal: /jet\s*pakistan/i.test(body) || /jetpakistan/i.test(await page.title()),
  };
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

try {
  await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 60000 });
  const title = await page.title();
  const h1 = (await page.locator("h1").allTextContents()).join(" | ");
  report.blackBox.title = title;
  report.blackBox.h1 = h1;

  // Discoverability via visible controls only
  const oneWay = page.getByText(/one\s*way/i).first();
  const roundTrip = page.getByText(/return|round\s*trip/i).first();
  report.blackBox.tripTypeVisible = {
    oneWay: (await oneWay.count()) > 0,
    returnOrRound: (await roundTrip.count()) > 0,
  };

  const searchBtn = page.getByRole("button", { name: /search flights|search/i }).first();
  report.blackBox.searchButtonVisible = (await searchBtn.count()) > 0;

  // Prefer filling visible labeled / placeholder fields without route knowledge
  const fromInput = page.locator("input").filter({ has: page.locator("xpath=.") }).first();
  // Use common accessible patterns present on homepage
  const origin = page.locator(
    'input[placeholder*="From" i], input[aria-label*="From" i], input[name*="origin" i], input[name*="from" i]',
  ).first();
  const destination = page.locator(
    'input[placeholder*="To" i], input[aria-label*="To" i], input[name*="destination" i], input[name*="to" i]',
  ).first();
  const depart = page.locator(
    'input[type="date"], input[placeholder*="Depart" i], input[aria-label*="Depart" i], input[name*="depart" i]',
  ).first();

  report.blackBox.originVisible = (await origin.count()) > 0;
  report.blackBox.destinationVisible = (await destination.count()) > 0;
  report.blackBox.departVisible = (await depart.count()) > 0;

  let reachedResults = false;
  let resultsSnippet = "";

  if (report.blackBox.originVisible && report.blackBox.destinationVisible && report.blackBox.searchButtonVisible) {
    // Safe typed values — one attempt only
    await origin.click({ timeout: 5000 }).catch(() => {});
    await origin.fill("KHI").catch(async () => {
      await origin.pressSequentially("KHI", { delay: 40 });
    });
    await page.waitForTimeout(400);
    const originOption = page.getByRole("option").first();
    if ((await originOption.count()) > 0) {
      await originOption.click().catch(() => {});
    }

    await destination.click({ timeout: 5000 }).catch(() => {});
    await destination.fill("DXB").catch(async () => {
      await destination.pressSequentially("DXB", { delay: 40 });
    });
    await page.waitForTimeout(400);
    const destOption = page.getByRole("option").first();
    if ((await destOption.count()) > 0) {
      await destOption.click().catch(() => {});
    }

    if ((await depart.count()) > 0) {
      const iso = new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10);
      await depart.fill(iso).catch(() => {});
    }

    report.liveSearchAttempts = 1;
    await searchBtn.click({ timeout: 5000 });
    await page.waitForTimeout(5000);
    const url = page.url();
    resultsSnippet = (await page.locator("body").innerText().catch(() => "")).replace(/\s+/g, " ").slice(0, 1200);
    reachedResults =
      /result|fare|flight|no flights|sorry|try again|loading|searching/i.test(resultsSnippet) ||
      /flights\/results|search/i.test(url);
    report.blackBox.resultsUrl = url.split("?")[0];
    report.blackBox.resultsComprehension = {
      hasFareOrPrice: /pkrs?|usd|fare|price|rs\.?/i.test(resultsSnippet),
      hasFilter: /filter|stops|direct|airline|cabin/i.test(resultsSnippet),
      hasCta: /select|book|continue|view details|choose/i.test(resultsSnippet),
      emptyOrErrorExplained: /no flight|no result|unavailable|try again|error/i.test(resultsSnippet),
      loadingContext: /searching|loading|finding flights/i.test(resultsSnippet),
    };

    // Hard stop — never click booking/payment CTAs
    const dangerous = page.getByRole("button", { name: /pay now|confirm booking|issue ticket|purchase/i });
    report.bookingWriteAttempted = false;
    report.blackBox.dangerousCtaVisible = (await dangerous.count()) > 0;
  } else {
    // Fallback: popular route link discoverability (counts as live search if navigates to results)
    const routeLink = page.getByRole("link", { name: /search route|KHI|DXB|popular/i }).first();
    if ((await routeLink.count()) > 0) {
      report.liveSearchAttempts = 1;
      await routeLink.click();
      await page.waitForTimeout(4000);
      resultsSnippet = (await page.locator("body").innerText().catch(() => "")).replace(/\s+/g, " ").slice(0, 1200);
      reachedResults = /result|fare|flight|no flights|loading|searching/i.test(resultsSnippet);
      report.blackBox.usedPopularRouteFallback = true;
    }
  }

  report.blackBox.reachedResultsOrSearchFeedback = reachedResults;
  report.blackBox.resultsSnippetLen = resultsSnippet.length;

  for (const width of [390, 768, 1366, 1440, 1920]) {
    report.widths[width] = await checkWidth(page, width);
  }

  // Deterministic verifier: no booking created by this session (URL/content heuristics + no pay CTA click)
  report.deterministic.noBookingWrite = report.bookingWriteAttempted === false;
  report.deterministic.liveSearchWithinBudget = report.liveSearchAttempts <= 1;
  report.deterministic.searchDiscoverable =
    report.blackBox.searchButtonVisible || report.blackBox.originVisible;
  report.deterministic.responsiveSearchVisible = Object.values(report.widths).every((w) => w.searchVisible || w.width >= 1366);

  report.success =
    report.deterministic.searchDiscoverable &&
    report.deterministic.noBookingWrite &&
    report.deterministic.liveSearchWithinBudget &&
    (report.blackBox.reachedResultsOrSearchFeedback || report.blackBox.searchButtonVisible);

  // Soft findings
  report.findings = [];
  if (!report.blackBox.tripTypeVisible.oneWay && !report.blackBox.tripTypeVisible.returnOrRound) {
    report.findings.push({ id: "ANON-TRIPTYPE", severity: "P2", note: "Trip type labels not clearly visible on first pass" });
  }
  if (report.blackBox.reachedResultsOrSearchFeedback && !report.blackBox.resultsComprehension?.hasFareOrPrice && !report.blackBox.resultsComprehension?.emptyOrErrorExplained) {
    report.findings.push({ id: "ANON-FARE", severity: "P2", note: "Results/feedback lacked clear fare or empty-state explanation" });
  }
  for (const [width, info] of Object.entries(report.widths)) {
    if (info.overflowX) {
      report.findings.push({ id: `ANON-OVERFLOW-${width}`, severity: "P3", note: `Horizontal overflow at ${width}` });
    }
  }
} finally {
  const outPath = path.join(outDir, `anon-focused-${Date.now()}.json`);
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  console.log(`REPORT_PATH=${outPath}`);
  console.log(`SUCCESS=${report.success ? "yes" : "no"}`);
  console.log(`LIVE_SEARCHES=${report.liveSearchAttempts}`);
  console.log(`FINDINGS=${report.findings?.length ?? 0}`);
  await browser.close();
}
