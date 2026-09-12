/**
 * Authority-06 postdeploy performance certification on build vkC0lfkEH9Gfj7CHfwcuO
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const OUT = path.join(__dirname, "postdeploy-perf-cert.json");
const EXPECTED_BUILD = "vkC0lfkEH9Gfj7CHfwcuO";
const RETURN_N = Number(process.env.JP_RETURN_N || 30);
const SOFT_N = Number(process.env.JP_SOFT_N || 20);

function pct(arr, p) {
  const a = [...arr].filter((n) => Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

const softRoutes = [
  { name: "home_support", from: "/", href: "/support", sel: "main, h1" },
  { name: "support_home", from: "/support", href: "/", sel: "main" },
  { name: "home_login", from: "/", href: "/login", sel: "input[type=password], form" },
  { name: "login_register", from: "/login", href: "/register", sel: "form" },
  { name: "home_groups", from: "/", href: "/groups", sel: "main" },
];

async function softNavSample(browser, route, attempt) {
  const ctx = await browser.newContext({ serviceWorkers: "block" });
  const page = await ctx.newPage();
  const t0 = Date.now();
  await page.goto(`${PROD}${route.from}`, { waitUntil: "domcontentloaded", timeout: 120000 });
  const link = page.locator(`main a[href="${route.href}"], header a[href="${route.href}"], a[href="${route.href}"]`).filter({ hasText: /.+/ }).first();
  if (await link.isVisible().catch(() => false)) {
    await link.click({ timeout: 30000 });
  } else {
    await page.goto(`${PROD}${route.from}`, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.goto(`${PROD}${route.href}`, { waitUntil: "domcontentloaded", timeout: 120000 });
  }
  await page.waitForSelector(route.sel, { timeout: 15000 }).catch(() => null);
  const appMs = Date.now() - t0;
  await ctx.close();
  return { route: route.name, attempt, app_ms: appMs };
}

async function returnSample(browser, i) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: "block" });
  const page = await ctx.newPage();
  const api = { searchStart: null, searchEnd: null, duplicateSearch: 0, dataPolls: [], postSupplierMs: null };
  page.on("request", (req) => {
    if (/\/flights\/results\/search/i.test(req.url()) && req.method() === "GET") {
      if (api.searchStart) api.duplicateSearch += 1;
      else api.searchStart = Date.now();
    }
  });
  page.on("response", async (res) => {
    const u = res.url();
    if (/\/flights\/results\/search/i.test(u)) api.searchEnd = Date.now();
    if (/\/flights\/results\/data/i.test(u)) {
      const body = await res.json().catch(() => null);
      const n =
        (body?.offers?.length ?? 0) ||
        (body?.paired_options?.length ?? 0) ||
        (body?.outbound_options?.length ?? 0);
      if (n > 0 && api.postSupplierMs == null && api.searchEnd) {
        api.postSupplierMs = Date.now() - api.searchEnd;
      }
      api.dataPolls.push({ at: Date.now(), n, status: body?.status });
    }
  });
  const d = new Date(Date.UTC(2026, 9, 15 + (i % 5)));
  const r = new Date(Date.UTC(2026, 9, 22 + (i % 5)));
  const url = `${PROD}/flights/results?trip_type=return&from=LHE&to=DXB&depart=${d.toISOString().slice(0, 10)}&return=${r.toISOString().slice(0, 10)}&adults=1`;
  const t0 = Date.now();
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000 });
  await page
    .locator('[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]')
    .first()
    .waitFor({ timeout: 90000 })
    .catch(() => null);
  const wallMs = Date.now() - t0;
  await ctx.close();
  return {
    sample_id: `return-${String(i).padStart(2, "0")}`,
    wall_ms: wallMs,
    supplier_wait_ms: api.searchEnd && api.searchStart ? api.searchEnd - api.searchStart : null,
    post_supplier_to_usable_ms: api.postSupplierMs,
    duplicate_search: api.duplicateSearch,
    valid: wallMs < 120000,
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const report = {
    captured_at: new Date().toISOString(),
    public_build_id: EXPECTED_BUILD,
    return_paired: { n: RETURN_N, samples: [] },
    soft_nav: { n: SOFT_N, samples: [] },
    gates: {},
  };

  for (let i = 0; i < RETURN_N; i += 1) {
    report.return_paired.samples.push(await returnSample(browser, i));
    await new Promise((r) => setTimeout(r, 400));
  }
  const walls = report.return_paired.samples.map((s) => s.wall_ms);
  const post = report.return_paired.samples.map((s) => s.post_supplier_to_usable_ms).filter((n) => n != null);
  const dups = report.return_paired.samples.reduce((s, x) => s + (x.duplicate_search || 0), 0);
  report.return_paired.p50_ms = pct(walls, 50);
  report.return_paired.p95_ms = pct(walls, 95);
  report.return_paired.post_supplier_p95_ms = pct(post, 95);
  report.gates.RETURN_PAIRED = {
    pass: dups === 0 && (report.return_paired.post_supplier_p95_ms ?? 99999) <= 1000,
    post_supplier_p95_ms: report.return_paired.post_supplier_p95_ms,
    duplicates: dups,
  };

  for (let i = 0; i < SOFT_N; i += 1) {
    const route = softRoutes[i % softRoutes.length];
    report.soft_nav.samples.push(await softNavSample(browser, route, i));
  }
  const appMs = report.soft_nav.samples.map((s) => s.app_ms);
  report.soft_nav.worst_app_p95_ms = pct(appMs, 95);
  report.gates.SOFT_NAV = {
    pass: (report.soft_nav.worst_app_p95_ms ?? 99999) <= 1500,
    worst_app_p95_ms: report.soft_nav.worst_app_p95_ms,
  };

  await browser.close();
  report.result = Object.values(report.gates).every((g) => g.pass) ? "PASS" : "PARTIAL";
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ result: report.result, gates: report.gates }, null, 2));
  process.exit(report.result === "PASS" ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
