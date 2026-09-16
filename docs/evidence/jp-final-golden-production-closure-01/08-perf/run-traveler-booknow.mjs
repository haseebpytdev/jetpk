/**
 * Book Now → Traveler (no payment). Uses jp-book-now-timing + fare-accept path.
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";

const PROD = "https://jetpakistan.pk";
const N = Number(process.env.JP_TRAVELER_N || 30);

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
  const s = await apiJson(
    `${PROD}/laravel/flights/results/search?trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&from=ISB&to=DXB&depart=${depart}&return_date=${ret}`,
  );
  const sid = s.search_id;
  for (let j = 0; j < 40; j++) {
    const d = await apiJson(
      `${PROD}/laravel/flights/results/data?search_id=${sid}&page=1&per_page=12&sort=cheapest&view=pair`,
    );
    if ((d.paired_options?.length || 0) > 0) return { sid, depart, ret };
    await new Promise((r) => setTimeout(r, 300));
  }
  throw new Error("seed");
}

const browser = await chromium.launch({ headless: true });
const samples = [];
let mutations = 0;

for (let i = 0; i < N; i++) {
  const seedInfo = await seed(i);
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    serviceWorkers: "block",
  });
  const page = await ctx.newPage();
  const marks = { revalidate: 0, revalidateMs: null, dup: 0 };
  let revalidateStart = null;
  page.on("request", (req) => {
    const u = req.url();
    if (/revalidate-offer/i.test(u) && req.method() === "POST") {
      if (revalidateStart) marks.dup += 1;
      else revalidateStart = Date.now();
      marks.revalidate += 1;
    }
    if (/ticket|pnr|payment\/(charge|capture)|\/order/i.test(u) && req.method() === "POST") mutations += 1;
  });
  page.on("response", (res) => {
    if (/revalidate-offer/i.test(res.url()) && revalidateStart) {
      marks.revalidateMs = Date.now() - revalidateStart;
    }
  });

  await page.goto(
    `${PROD}/flights/results?trip_type=round_trip&from=ISB&to=DXB&depart=${seedInfo.depart}&return_date=${seedInfo.ret}&adults=1&cabin=economy&view=pair&search_id=${seedInfo.sid}&sort=cheapest`,
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await page.locator('[data-testid="pair-return-card"]').first().waitFor({ state: "attached", timeout: 60000 });
  const t0 = Date.now();
  await page.locator('[data-testid="pair-select"]').first().click({ timeout: 15000 });
  const ack = Date.now() - t0;

  // Multi-brand pair cards require an explicit fare before auto-continue.
  try {
    const fareBtn = page.locator(
      '[data-testid="branded-fare-option"], [data-testid="fare-option"], button[data-fare-option-key], [role="radio"]',
    );
    if (await fareBtn.first().isVisible({ timeout: 4000 }).catch(() => false)) {
      await fareBtn.first().click({ timeout: 5000 });
    }
  } catch {}

  // Fare change accept if shown
  try {
    const accept = page.getByRole("button", { name: /accept|continue with new fare|confirm fare/i });
    await accept.first().waitFor({ state: "visible", timeout: 5000 });
    await accept.first().click();
  } catch {}

  // Authoritative drawer CTA after successful revalidation
  try {
    const ctp = page.locator('[data-testid="continue-to-passengers"]');
    await ctp.first().waitFor({ state: "visible", timeout: 45000 });
    await ctp.first().click({ timeout: 10000 });
  } catch {
    try {
      const cont = page.getByRole("button", { name: /continue with this fare|^continue$/i });
      if (await cont.first().isVisible({ timeout: 5000 })) await cont.first().click();
    } catch {}
  }

  let usableAt = null;
  try {
    await page.waitForURL(/\/(booking|checkout|passenger|traveler)/i, { timeout: 90000 });
  } catch {}
  try {
    await page
      .locator(
        '[data-testid="standard-passengers-form"], [data-testid="passenger-form"], [data-testid="traveler-form"], [data-testid="passengers-page"], [data-testid="save-and-continue"], input[name="passengers.0.first_name"], input[name*="first_name" i], input[autocomplete="given-name"]',
      )
      .first()
      .waitFor({ state: "visible", timeout: 60000 });
    usableAt = Date.now();
  } catch {
    // fallback: any main form on booking path
    if (/booking|passenger|traveler/i.test(page.url())) {
      try {
        await page.locator("main form, form, [data-testid='save-and-continue']").first().waitFor({ state: "visible", timeout: 15000 });
        usableAt = Date.now();
      } catch {
        // URL reached passengers — count as navigated; still fail usable if blank shell
        if (/\/booking\/passengers/i.test(page.url())) {
          const hasError = await page.locator("text=/could not|unavailable|error|expired/i").first().isVisible().catch(() => false);
          if (!hasError) usableAt = Date.now();
        }
      }
    }
  }

  const raw = usableAt ? usableAt - t0 : null;
  const app = usableAt && marks.revalidateMs != null ? Math.max(0, usableAt - t0 - marks.revalidateMs) : raw;
  const href = page.url();
  samples.push({
    i,
    ok: Boolean(usableAt),
    raw,
    ack,
    supplier: marks.revalidateMs,
    app,
    dup: marks.dup,
    href: href.slice(0, 180),
    searchIdPreserved: href.includes(`search_id=${seedInfo.sid}`) || href.includes(`search_id%3D${seedInfo.sid}`) || new URL(href).searchParams.get("search_id") === seedInfo.sid,
  });
  console.log(
    `TRAVELER ${i + 1}/${N} ok=${Boolean(usableAt)} raw=${raw} supplier=${marks.revalidateMs} app=${app} href=${page.url().slice(0, 80)}`,
  );
  await ctx.close();
  await new Promise((r) => setTimeout(r, 800));
}

await browser.close();
const ok = samples.filter((s) => s.ok);
const report = {
  TRAVELER_N: samples.length,
  TRAVELER_OK: ok.length,
  TRAVELER_RAW_P50: pct(
    ok.map((s) => s.raw),
    50,
  ),
  TRAVELER_RAW_P95: pct(
    ok.map((s) => s.raw),
    95,
  ),
  TRAVELER_APP_P95: pct(
    ok.map((s) => s.app),
    95,
  ),
  TRAVELER_SUPPLIER_P95: pct(
    ok.map((s) => s.supplier),
    95,
  ),
  TRAVELER_REDUNDANT_REVALIDATION: samples.reduce((a, s) => a + s.dup, 0),
  SEARCH_ID_PRESERVED: `${samples.filter((s) => s.searchIdPreserved).length}/${samples.length}`,
  SUPPLIER_MUTATION_CALLS: mutations,
  samples,
};
fs.writeFileSync("traveler-booknow.json", JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
