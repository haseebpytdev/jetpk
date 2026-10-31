import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";

function pct(arr, p) {
  const a = [...arr].sort((x, y) => x - y);
  const i = (a.length - 1) * (p / 100);
  const lo = Math.floor(i);
  const hi = Math.ceil(i);
  if (lo === hi) return a[lo];
  return a[lo] + (a[hi] - a[lo]) * (i - lo);
}

async function run(baseUrl, n = 30) {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await page.goto(`${baseUrl}/`, { waitUntil: "networkidle", timeout: 120000 });
  await page.waitForTimeout(2000);
  await page.locator('a[href="/groups/search"]').first().click();
  await page.waitForURL(/\/groups\/search/);
  await page.goto(`${baseUrl}/`, { waitUntil: "networkidle" });
  await page.waitForTimeout(1000);
  const hg = [];
  const gh = [];
  for (let i = 0; i < n; i++) {
    let t0 = Date.now();
    await page.locator('a[href="/groups/search"]').first().click();
    await page.waitForURL(/\/groups\/search/, { timeout: 30000 });
    await page.waitForLoadState("domcontentloaded");
    hg.push(Date.now() - t0);
    t0 = Date.now();
    await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded" });
    await page.waitForLoadState("domcontentloaded");
    gh.push(Date.now() - t0);
  }
  await browser.close();
  return {
    n,
    homeGroups: { median: Math.round(pct(hg, 50)), p95: Math.round(pct(hg, 95)), samples: hg },
    groupsHome: { median: Math.round(pct(gh, 50)), p95: Math.round(pct(gh, 95)), samples: gh },
  };
}

const base = await run("http://127.0.0.1:3010");
const fin = await run("http://127.0.0.1:3011");
const abs = fin.homeGroups.p95 - base.homeGroups.p95;
const rel = base.homeGroups.p95 ? abs / base.homeGroups.p95 : 0;
const out = {
  base,
  final: fin,
  homeGroupsAbsDelta: abs,
  homeGroupsRelPercent: Number((rel * 100).toFixed(2)),
  GROUPS_SOFT_NAV_MATERIAL_REGRESSION: abs > 100 && rel > 0.15 ? "YES" : "NO",
};
fs.writeFileSync(
  "docs/evidence/jp-ui-perf-stability-01d3-group-prefetch/01-soft-nav-comparison.json",
  JSON.stringify(out, null, 2),
);
console.log(JSON.stringify(out, null, 2));
