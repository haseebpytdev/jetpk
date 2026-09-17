/**
 * Seeded Return Pair browser N + Pair↔Segmented switch (no supplier mutations).
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";

const PROD = "https://jetpakistan.pk";
const RETURN_N = Number(process.env.JP_RETURN_N || 30);
const SWITCH_N = Number(process.env.JP_SWITCH_N || 20);

function pct(arr, p) {
  const a = [...arr].filter(Number.isFinite).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function apiJson(url) {
  const res = await fetch(url, { headers: { Accept: "application/json", "User-Agent": "jp-closure" } });
  let t = await res.text();
  if (t.charCodeAt(0) === 0xfeff) t = t.slice(1);
  return JSON.parse(t);
}

async function seed(i) {
  const depart = `2026-11-${String(4 + (i % 6)).padStart(2, "0")}`;
  const ret = `2026-11-${String(11 + (i % 6)).padStart(2, "0")}`;
  const tSearch = Date.now();
  const s = await apiJson(
    `${PROD}/laravel/flights/results/search?trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&from=ISB&to=DXB&depart=${depart}&return_date=${ret}`,
  );
  const sid = s.search_id;
  let supplierEnd = null;
  for (let j = 0; j < 50; j++) {
    const d = await apiJson(
      `${PROD}/laravel/flights/results/data?search_id=${sid}&page=1&per_page=12&sort=cheapest&view=pair`,
    );
    if ((d.paired_options?.length || 0) > 0) {
      supplierEnd = Date.now();
      return { sid, depart, ret, searchStart: tSearch, supplierEnd };
    }
    await new Promise((r) => setTimeout(r, 300));
  }
  throw new Error("seed");
}

const browser = await chromium.launch({ headless: true });
const returns = [];
let mutations = 0;

for (let i = 0; i < RETURN_N; i++) {
  const seedInfo = await seed(i);
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: "block" });
  const page = await ctx.newPage();
  let dup = 0;
  let searchHits = 0;
  page.on("request", (req) => {
    if (/\/flights\/results\/search/i.test(req.url()) && req.method() === "GET") {
      searchHits += 1;
      if (searchHits > 1) dup += 1;
    }
    if (/ticket|pnr|payment\/(charge|capture)|\/order/i.test(req.url()) && req.method() === "POST") mutations += 1;
  });
  const t0 = Date.now();
  await page.goto(
    `${PROD}/flights/results?trip_type=round_trip&from=ISB&to=DXB&depart=${seedInfo.depart}&return_date=${seedInfo.ret}&adults=1&cabin=economy&view=pair&search_id=${seedInfo.sid}&sort=cheapest`,
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  let firstUsefulAt = null;
  try {
    await page.locator('[data-testid="pair-return-card"]').first().waitFor({ state: "visible", timeout: 60000 });
    firstUsefulAt = Date.now();
  } catch {}
  const outbound = await page.locator('[data-leg="outbound"]').count();
  const retLegs = await page.locator('[data-leg="return"]').count();
  const pairs = await page.locator('[data-testid="pair-return-card"]').count();
  const outboundOption = await page.locator('[data-testid="outbound-option-card"]').count();
  const post = firstUsefulAt && seedInfo.supplierEnd ? firstUsefulAt - seedInfo.supplierEnd : null;
  // Browser-only: navigation start → first card (inventory already seeded).
  const browserRender = firstUsefulAt ? firstUsefulAt - t0 : null;
  returns.push({
    i,
    raw: firstUsefulAt ? firstUsefulAt - seedInfo.searchStart : null,
    supplier: seedInfo.supplierEnd - seedInfo.searchStart,
    postSupplier: post,
    browserRender,
    pairs,
    outbound,
    retLegs,
    outboundOption,
    wrong: pairs > 0 && (outbound === 0 || retLegs === 0) ? 1 : 0,
    dup,
  });
  console.log(
    `RETURN ${i + 1}/${RETURN_N} post=${post} browser=${browserRender} pairs=${pairs} wrong=${pairs > 0 && (outbound === 0 || retLegs === 0) ? 1 : 0} dup=${dup}`,
  );
  await ctx.close();
  await new Promise((r) => setTimeout(r, 600));
}

const switches = [];
for (let i = 0; i < SWITCH_N; i++) {
  const seedInfo = await seed(100 + i);
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: "block" });
  const page = await ctx.newPage();
  let supplierCalls = 0;
  page.on("request", (req) => {
    if (/\/flights\/results\/search/i.test(req.url()) && req.method() === "GET") supplierCalls += 1;
  });
  await page.goto(
    `${PROD}/flights/results?trip_type=round_trip&from=ISB&to=DXB&depart=${seedInfo.depart}&return_date=${seedInfo.ret}&adults=1&cabin=economy&view=pair&search_id=${seedInfo.sid}&sort=cheapest`,
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await page.locator('[data-testid="pair-return-card"]').first().waitFor({ state: "visible", timeout: 60000 });
  supplierCalls = 0;
  const t1 = Date.now();
  await page.getByRole("button", { name: /Segmented/i }).click();
  await page
    .locator('[data-testid="outbound-option-card"], [data-leg="outbound"]')
    .first()
    .waitFor({ state: "visible", timeout: 15000 })
    .catch(() => null);
  const pairToSeg = Date.now() - t1;
  const t2 = Date.now();
  await page.getByRole("button", { name: /^Pair$/i }).click();
  await page.locator('[data-testid="pair-return-card"]').first().waitFor({ state: "visible", timeout: 15000 });
  const segToPair = Date.now() - t2;
  switches.push({ i, pairToSeg, segToPair, supplierCalls });
  console.log(`SWITCH ${i + 1}/${SWITCH_N} p2s=${pairToSeg} s2p=${segToPair} supplier=${supplierCalls}`);
  await ctx.close();
  await new Promise((r) => setTimeout(r, 500));
}

await browser.close();
const report = {
  RETURN_N: returns.length,
  RETURN_RAW_P50: pct(
    returns.map((r) => r.raw),
    50,
  ),
  RETURN_RAW_P95: pct(
    returns.map((r) => r.raw),
    95,
  ),
  RETURN_SUPPLIER_P95: pct(
    returns.map((r) => r.supplier),
    95,
  ),
  RETURN_POST_SUPPLIER_TO_USABLE_P50: pct(
    returns.map((r) => r.postSupplier),
    50,
  ),
  RETURN_POST_SUPPLIER_TO_USABLE_P95: pct(
    returns.map((r) => r.postSupplier),
    95,
  ),
  RETURN_BROWSER_RENDER_P95: pct(
    returns.map((r) => r.browserRender),
    95,
  ),
  RETURN_DUPLICATE_SEARCHES: returns.reduce((a, r) => a + r.dup, 0),
  RETURN_WRONG_ITINERARY: returns.reduce((a, r) => a + r.wrong, 0),
  PAIR_TO_SEGMENTED_P95: pct(
    switches.map((s) => s.pairToSeg),
    95,
  ),
  SEGMENTED_TO_PAIR_P95: pct(
    switches.map((s) => s.segToPair),
    95,
  ),
  RETURN_VIEW_SWITCH_SUPPLIER_CALLS: switches.reduce((a, s) => a + s.supplierCalls, 0),
  SUPPLIER_MUTATION_CALLS: mutations,
  returns,
  switches,
};
fs.writeFileSync("seeded-return-switch.json", JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
