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
  await page.addInitScript(() => {
    window.__jpD2R = { dataAt: null, cardAt: null };
    const orig = Response.prototype.json;
    Response.prototype.json = async function (...args) {
      const data = await orig.apply(this, args);
      try {
        if (
          window.__jpD2R.dataAt == null &&
          this.url &&
          /results\/data/i.test(this.url) &&
          (data?.paired_options?.length || 0) > 0
        ) {
          window.__jpD2R.dataAt = performance.now();
        }
      } catch {}
      return data;
    };
    const markCard = () => {
      if (window.__jpD2R.cardAt != null) return;
      if (document.querySelector('[data-testid="pair-return-card"]')) {
        window.__jpD2R.cardAt = performance.now();
      }
    };
    const mo = new MutationObserver(markCard);
    mo.observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener("DOMContentLoaded", markCard);
  });
  await page.goto(
    `${PROD}/flights/results?trip_type=round_trip&from=ISB&to=DXB&depart=${seedInfo.depart}&return_date=${seedInfo.ret}&adults=1&cabin=economy&view=pair&search_id=${seedInfo.sid}&sort=cheapest`,
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await page.waitForFunction(() => {
    return Boolean(window.__jpD2R?.cardAt != null && window.__jpD2R?.dataAt != null);
  }, null, { timeout: 60000 });
  const { cardAt, dataAt } = await page.evaluate(() => ({
    cardAt: window.__jpD2R?.cardAt ?? null,
    dataAt: window.__jpD2R?.dataAt ?? null,
  }));
  const ms = dataAt != null && cardAt != null ? Math.round(cardAt - dataAt) : null;
  samples.push({ i, ms });
  console.log("PURE", i + 1, ms);
  await ctx.close();
  await new Promise((r) => setTimeout(r, 300));
}
await browser.close();
const warm = samples.map((s) => s.ms).filter((n) => n != null && n >= 0);
const report = {
  N: samples.length,
  DATA_TO_RENDER_P50: pct(warm, 50),
  DATA_TO_RENDER_P95: pct(warm, 95),
  samples,
};
fs.writeFileSync("data-to-render-pure.json", JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
