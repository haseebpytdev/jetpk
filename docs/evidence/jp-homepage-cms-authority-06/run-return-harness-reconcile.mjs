/**
 * Return harness reconciliation — compare broken vs known-good flow on production.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "return-harness-reconcile.json");
const PROD = "https://jetpakistan.pk";
const CARD =
  '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]';

function dates(i) {
  const d = new Date(Date.UTC(2026, 9, 15 + (i % 3)));
  const r = new Date(Date.UTC(2026, 9, 22 + (i % 3)));
  return { depart: d.toISOString().slice(0, 10), ret: r.toISOString().slice(0, 10) };
}

async function brokenFlow(page, i) {
  const { depart, ret } = dates(i);
  const url = `${PROD}/flights/results?trip_type=return&from=LHE&to=DXB&depart=${depart}&return=${ret}&adults=1`;
  const t0 = Date.now();
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000 });
  let searchId = null;
  let backendStatus = null;
  let resultCount = 0;
  page.on("response", async (res) => {
    const u = res.url();
    if (/\/flights\/results\/search/i.test(u)) {
      const b = await res.json().catch(() => null);
      searchId = b?.search_id ?? searchId;
    }
    if (/\/flights\/results\/data/i.test(u)) {
      const b = await res.json().catch(() => null);
      backendStatus = b?.status ?? b?.data?.status ?? backendStatus;
      resultCount = Math.max(resultCount, (b?.paired_options?.length ?? 0) + (b?.offers?.length ?? 0));
    }
  });
  const cardCount = await page.locator(CARD).count().catch(() => 0);
  await page.waitForTimeout(15000);
  const cardCount15 = await page.locator(CARD).count().catch(() => 0);
  const selectors = await page.evaluate(() =>
    [...document.querySelectorAll("[data-testid]")]
      .map((el) => el.getAttribute("data-testid"))
      .filter((t) => /flight|pair|return|outbound|result/i.test(t || ""))
      .slice(0, 20),
  );
  return {
    flow: "BROKEN_POSTDEPLOY",
    url,
    search_id: searchId,
    backend_status: backendStatus,
    result_count: resultCount,
    visible_result_card_count: cardCount15,
    harness_selector: CARD,
    actual_result_selectors: selectors,
    first_result_dom_ms: cardCount15 > 0 ? Date.now() - t0 : null,
    classification: cardCount15 > 0 ? "A_SELECTOR_MISS_EARLIER" : resultCount > 0 ? "C_BACKEND_READY_NO_RENDER" : "B_OR_D",
    elapsed_ms: Date.now() - t0,
  };
}

async function goodFlow(page, i) {
  const { depart, ret } = dates(i);
  const criteria =
    `from=ISB&to=DXB&depart=${depart}&return_date=${ret}` +
    `&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair`;
  const t0 = Date.now();
  let searchId = null;
  let backendStatus = null;
  let resultCount = 0;
  let firstDataAt = null;
  page.on("response", async (res) => {
    const u = res.url();
    if (/\/flights\/results\/search/i.test(u)) {
      const b = await res.json().catch(() => null);
      searchId = b?.search_id ?? searchId;
    }
    if (/\/flights\/results\/data/i.test(u) || /\/flights\/return-options\/data/i.test(u)) {
      const b = await res.json().catch(() => null);
      const n =
        (b?.paired_options?.length ?? 0) || (b?.offers?.length ?? 0) || (b?.outbound_options?.length ?? 0);
      backendStatus = b?.status ?? b?.data?.status ?? backendStatus;
      resultCount = Math.max(resultCount, n);
      if (n > 0 && !firstDataAt) firstDataAt = Date.now();
    }
  });
  await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
  let initId = null;
  try {
    const init = await page.request.get(`${PROD}/laravel/flights/results/search?${criteria}`);
    initId = (await init.json())?.search_id ?? null;
  } catch {
    /* ignore */
  }
  const url = `${PROD}/flights/results?${criteria}${initId ? `&search_id=${initId}` : ""}`;
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000 });
  let firstCardAt = null;
  try {
    await page.waitForSelector(CARD, { timeout: 120000 });
    firstCardAt = Date.now();
  } catch {
    /* timeout */
  }
  const selectors = await page.evaluate(() =>
    [...document.querySelectorAll("[data-testid]")]
      .map((el) => el.getAttribute("data-testid"))
      .filter((t) => /flight|pair|return|outbound|result/i.test(t || ""))
      .slice(0, 20),
  );
  const visible = await page.locator(CARD).count();
  return {
    flow: "KNOWN_GOOD_RETURN_N30",
    url,
    search_id: searchId || initId,
    backend_status: backendStatus,
    result_count: resultCount,
    visible_result_card_count: visible,
    harness_selector: CARD,
    actual_result_selectors: selectors,
    first_result_dom_timestamp_ms: firstCardAt ? firstCardAt - t0 : null,
    first_result_visual_timestamp_ms: firstCardAt ? firstCardAt - t0 : null,
    first_useful_data_ms: firstDataAt ? firstDataAt - t0 : null,
    classification: visible > 0 ? "A_HARNESS_OK_WITH_CORRECT_FLOW" : resultCount > 0 ? "C_BACKEND_READY_NO_RENDER" : "B_OR_D",
    elapsed_ms: Date.now() - t0,
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const samples = [];
  for (let i = 0; i < 3; i += 1) {
    const ctx1 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const p1 = await ctx1.newPage();
    samples.push(await brokenFlow(p1, i));
    await ctx1.close();
    const ctx2 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const p2 = await ctx2.newPage();
    samples.push(await goodFlow(p2, i));
    await ctx2.close();
  }
  const report = {
    captured_at: new Date().toISOString(),
    public_build_id: "vkC0lfkEH9Gfj7CHfwcuO",
    RETURN_HARNESS_ROOT_CAUSE:
      "Broken postdeploy runner used trip_type=return + hard-goto LHE-DXB without warm init; selectors valid when round_trip view=pair flow used",
    RETURN_HARNESS_SELECTOR_VALID: samples.some((s) => s.flow === "KNOWN_GOOD_RETURN_N30" && s.visible_result_card_count > 0)
      ? "PASS"
      : "FAIL",
    samples,
  };
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
  process.exit(report.RETURN_HARNESS_SELECTOR_VALID === "PASS" ? 0 : 1);
}

main();
