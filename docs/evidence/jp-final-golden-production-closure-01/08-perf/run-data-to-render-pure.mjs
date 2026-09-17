/**
 * DATA→first useful card using in-app marks (__jpD2r*) with Playwright fallback.
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";

const PROD = "https://jetpakistan.pk";
const N = Number(process.env.JP_RENDER_N || 20);

function pct(arr, p) {
  const a = [...arr].filter(Number.isFinite).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function apiJson(url) {
  const res = await fetch(url, { headers: { Accept: "application/json" } });
  let t = await res.text();
  if (t.charCodeAt(0) === 0xfeff) t = t.slice(1);
  return JSON.parse(t);
}

async function seed(i) {
  const depart = `2026-12-${String(10 + (i % 8)).padStart(2, "0")}`;
  const ret = `2026-12-${String(17 + (i % 8)).padStart(2, "0")}`;
  const s = await apiJson(
    `${PROD}/laravel/flights/results/search?trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&from=ISB&to=DXB&depart=${depart}&return_date=${ret}`,
  );
  for (let j = 0; j < 50; j++) {
    const d = await apiJson(
      `${PROD}/laravel/flights/results/data?search_id=${s.search_id}&page=1&per_page=12&sort=cheapest&view=pair`,
    );
    if ((d.paired_options?.length || 0) > 0) return { sid: s.search_id, depart, ret };
    await new Promise((r) => setTimeout(r, 300));
  }
  throw new Error("seed");
}

const browser = await chromium.launch({ headless: true });
const samples = [];
for (let i = 0; i < N; i++) {
  const seedInfo = await seed(i);
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    serviceWorkers: "block",
  });
  const page = await ctx.newPage();
  let networkDataAt = null;
  page.on("response", async (res) => {
    if (!/results\/data/i.test(res.url())) return;
    if (networkDataAt != null) return;
    try {
      const j = await res.json();
      if ((j?.paired_options?.length || 0) > 0) networkDataAt = Date.now();
    } catch {}
  });
  await page.goto(
    `${PROD}/flights/results?trip_type=round_trip&from=ISB&to=DXB&depart=${seedInfo.depart}&return_date=${seedInfo.ret}&adults=1&cabin=economy&view=pair&search_id=${seedInfo.sid}&sort=cheapest`,
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await page.locator('[data-testid="pair-return-card"]').first().waitFor({ state: "attached", timeout: 60000 });
  const cardAt = Date.now();
  const marks = await page.evaluate(() => {
    const w = window;
    return {
      dataReceivedAt: w.__jpD2rDataReceivedAt ?? null,
      dataAt: w.__jpD2rDataAt ?? null,
      cardAt: w.__jpD2rCardAt ?? null,
      flushMs: w.__jpD2rFlushMs ?? null,
    };
  });
  let ms = null;
  let source = "network_fallback";
  if (marks.dataReceivedAt != null && marks.cardAt != null) {
    ms = Math.max(0, marks.cardAt - marks.dataReceivedAt);
    source = "in_app_marks";
  } else if (marks.flushMs != null) {
    ms = marks.flushMs;
    source = "flush_ms";
  } else if (networkDataAt != null) {
    ms = Math.max(0, cardAt - networkDataAt);
    source = "playwright_network";
  }
  samples.push({ i, ms, source, marks, networkDataAt, cardAt });
  console.log(`D2R ${i + 1}/${N} ms=${ms} source=${source}`);
  await ctx.close();
}
await browser.close();
const vals = samples.map((s) => s.ms).filter(Number.isFinite);
const report = {
  N: samples.length,
  DATA_TO_RENDER_P50: pct(vals, 50),
  DATA_TO_RENDER_P95: pct(vals, 95),
  samples,
};
fs.writeFileSync("data-to-render-pure.json", JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
