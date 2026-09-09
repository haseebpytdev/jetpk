/**
 * Authority-06 postdeploy public certification (read-only + safe browser).
 * Build: vkC0lfkEH9Gfj7CHfwcuO | SHA: f7d37b6c
 */
import { chromium, devices } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const EXPECTED_BUILD = "vkC0lfkEH9Gfj7CHfwcuO";
const EXPECTED_SHA = "f7d37b6cd641db66671ba02d7c95dc4591643b51";
const OUT = path.join(__dirname, "postdeploy-public-cert.json");
const SCREEN = path.join(__dirname, "screenshots", "postdeploy");

const report = {
  captured_at: new Date().toISOString(),
  engineering_sha: EXPECTED_SHA,
  public_build_id: EXPECTED_BUILD,
  gates: {},
  samples: {},
  links: [],
  console: [],
};

function gate(name, pass, detail = {}) {
  report.gates[name] = { pass, ...detail };
}

async function shot(page, name) {
  fs.mkdirSync(SCREEN, { recursive: true });
  const file = path.join(SCREEN, name);
  try {
    await page.screenshot({ path: file, fullPage: false, timeout: 60000, animations: "disabled" });
    return path.relative(__dirname, file).replace(/\\/g, "/");
  } catch {
    return null;
  }
}

function pct(arr, p) {
  const a = [...arr].filter((n) => Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function fetchHome() {
  const res = await fetch(`${PROD}/api/public/content/homepage`, { headers: { Accept: "application/json" } });
  return { status: res.status, body: await res.json() };
}

async function freshHomeSample(i, browser) {
  const ctx = await browser.newContext({ serviceWorkers: "block" });
  const page = await ctx.newPage();
  const t0 = Date.now();
  await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
  const navMs = Date.now() - t0;
  const perf = await page.evaluate(() => {
    const nav = performance.getEntriesByType("navigation")[0];
    const paints = performance.getEntriesByType("paint");
    const fcp = paints.find((p) => p.name === "first-contentful-paint")?.startTime ?? null;
    const hero = !!document.querySelector('[data-testid="homepage-hero"], .jp-section-hero, main');
    const search = !!document.querySelector('form, [data-testid="flight-search-form"], input[placeholder*="From" i]');
    const destImgs = [...document.querySelectorAll('img[src*="destination"], img[src*="client-assets"]')].length;
    return { fcp, hero, search, destImgs, build: document.documentElement.innerHTML.includes("vkC0lfkEH9Gfj7CHfwcuO") };
  });
  await ctx.close();
  return { i, navMs, ...perf };
}

async function probeTrendingBrowser(page, route, index) {
  const started = Date.now();
  const api = { searchIds: new Set(), duplicateSearches: 0, dataPolls: [], terminal: null };
  page.on("response", async (res) => {
    const u = res.url();
    if (/\/flights\/results\/search/i.test(u)) {
      const m = u.match(/search_id=([^&]+)/);
      if (m?.[1]) {
        if (api.searchIds.size && !api.searchIds.has(m[1])) api.duplicateSearches += 1;
        api.searchIds.add(m[1]);
      }
    }
    if (/\/flights\/results\/data/i.test(u)) {
      const body = await res.json().catch(() => null);
      const status = String(body?.status ?? body?.data?.status ?? "").toLowerCase();
      const n =
        (body?.offers?.length ?? 0) ||
        (body?.data?.offers?.length ?? 0) ||
        (body?.paired_options?.length ?? 0) ||
        (body?.outbound_options?.length ?? 0);
      api.dataPolls.push({ status, n, http: res.status() });
      if (["ready", "empty", "failed", "expired", "error"].includes(status)) {
        api.terminal = status === "ready" && n > 0 ? "success" : status === "empty" ? "no_results" : status;
      } else if (n > 0) api.terminal = "success";
    }
  });
  await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
  const card = page.locator('[data-testid="trending-route-card"] a').nth(index);
  if (await card.count()) {
    await card.click({ timeout: 30000 });
  } else {
    const searchUrl = route.search_url || route.cta_url;
    if (!searchUrl) return { route: route.id, skipped: true };
    await page.goto(`${PROD}${searchUrl.startsWith("/") ? searchUrl : `/${searchUrl}`}`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
  }
  while (Date.now() - started < 70000 && !api.terminal) {
    const cards = await page.locator('[data-testid="flight-result-card"], [data-testid="pair-return-card"]').count();
    if (cards > 0) api.terminal = "success";
    await page.waitForTimeout(1000);
  }
  const terminal = api.terminal ?? "searching";
  return {
    route: route.id,
    from: route.from,
    to: route.to,
    search_id: [...api.searchIds][0] ?? null,
    poll_count: api.dataPolls.length,
    backend_terminal_status: api.dataPolls.at(-1)?.status ?? null,
    frontend_terminal_status: terminal,
    infinite_searching: terminal === "searching",
    duplicate_searches: api.duplicateSearches,
    total_wait_ms: Date.now() - started,
  };
}

async function crawlLinks(page) {
  const hrefs = await page.evaluate(() => {
    const out = [];
    for (const a of document.querySelectorAll("header a[href], footer a[href], main a[href]")) {
      const href = a.getAttribute("href");
      const text = (a.textContent || "").trim().slice(0, 80);
      if (href && !href.startsWith("#") && !href.startsWith("mailto:") && !href.startsWith("tel:")) {
        out.push({ text, href });
      }
    }
    return out;
  });
  const seen = new Set();
  const results = [];
  for (const item of hrefs) {
    let url = item.href;
    if (url.startsWith("/")) url = `${PROD}${url}`;
    if (!url.startsWith(PROD) || seen.has(url)) continue;
    seen.add(url);
    if (/logout|booking\/payment|bookings\/create/i.test(url)) continue;
    const t0 = Date.now();
    const res = await page.request.get(url, { maxRedirects: 5, timeout: 45000 });
    results.push({
      source: "homepage_nav",
      text: item.text,
      href: item.href,
      final_url: res.url(),
      http_status: res.status(),
      load_ms: Date.now() - t0,
      legacy_leakage: /parwaaz|yoursdomain|haseeb-master/i.test(await res.text().catch(() => "")),
    });
  }
  return results;
}

async function main() {
  fs.mkdirSync(SCREEN, { recursive: true });
  const homeApi = await fetchHome();
  gate("HOMEPAGE_API_200", homeApi.status === 200, { status: homeApi.status });
  const home = homeApi.body ?? {};
  const featured = home.featured_deals?.items ?? [];
  const legacy = featured.filter((f) => String(f.href ?? "").includes("/groups/package/"));
  const unavailable = featured.filter((f) => f.availability === "unavailable" || !f.href);
  gate("FEATURED_UNAVAILABLE_COUNT_ZERO", unavailable.length === 0, { count: unavailable.length });
  gate("FEATURED_LEGACY_UI_VISIBLE_NO", legacy.length === 0, { legacy_count: legacy.length });

  const destCodes = ["DXB", "JED", "LHR", "IST"];
  for (const code of destCodes) {
    const item = (home.destinations?.items ?? []).find((d) => String(d.code || d.destination || d.to).toUpperCase() === code);
    const img = item?.image || item?.img || "";
    let http200 = false;
    if (img) {
      const imgRes = await fetch(`${PROD}${img.startsWith("/") ? img : `/${img}`}`, { method: "HEAD" });
      http200 = imgRes.ok;
    }
    gate(`DESTINATION_${code}_API_IMAGE`, Boolean(img) && http200, { image: img, http: http200 ? 200 : 0 });
  }

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ serviceWorkers: "block" });
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  page.on("console", (m) => {
    if (m.type() === "error") report.console.push(m.text().slice(0, 200));
  });

  await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
  const html = await page.content();
  gate("PUBLIC_BUILD_ID_IN_HTML", html.includes(EXPECTED_BUILD), { found: html.includes(EXPECTED_BUILD) });
  const iconHref = await page.locator('link[rel="icon"], link[rel="shortcut icon"]').first().getAttribute("href");
  let faviconHttp = 0;
  if (iconHref) {
    const favUrl = iconHref.startsWith("http") ? iconHref : `${PROD}${iconHref.startsWith("/") ? iconHref : `/${iconHref}`}`;
    const fr = await page.request.get(favUrl);
    faviconHttp = fr.status();
  }
  gate("FAVICON_HEAD_REFERENCE", Boolean(iconHref), { href: iconHref });
  gate("FAVICON_HTTP_200", faviconHttp === 200, { status: faviconHttp });
  await shot(page, "home-desktop.png");

  const registerVisible = await page.locator('header a[href*="/register"], header a:has-text("Register")').count();
  const loginVisible = await page.locator('header a[href*="/login"], header a:has-text("Log in"), header a:has-text("Login")').count();
  gate("HEADER_REGISTER_VISIBLE_NO", registerVisible === 0, { count: registerVisible });
  gate("HEADER_LOGIN_VISIBLE", loginVisible > 0, { count: loginVisible });

  const pageHtml = await page.content();
  for (const code of destCodes) {
    const apiItem = (home.destinations?.items ?? []).find((d) => String(d.code || d.destination || d.to).toUpperCase() === code);
    const apiImg = apiItem?.image || apiItem?.img || "";
    const apiKey = apiImg ? path.basename(apiImg.split("?")[0]) : "";
    const domOk = apiKey && pageHtml.includes(apiKey);
    gate(`DESTINATION_${code}_DOM_PARITY`, Boolean(domOk), { apiImg, domKey: apiKey });
  }

  const trending = (home.routes?.items ?? []).filter((r) => String(r.enabled ?? "1") !== "0");
  const trendingResults = [];
  for (let i = 0; i < trending.length; i += 1) {
    const tpage = await browser.newPage();
    trendingResults.push(await probeTrendingBrowser(tpage, trending[i], i));
    await tpage.close();
  }
  const infinite = trendingResults.filter((r) => r.infinite_searching).length;
  const dup = trendingResults.reduce((s, r) => s + (r.duplicate_searches || 0), 0);
  gate("TRENDING_INFINITE_SEARCHING_ZERO", infinite === 0, { infinite, trendingResults });
  gate("TRENDING_DUPLICATE_SEARCH_ZERO", dup === 0, { dup });
  report.samples.trending = trendingResults;

  if (featured[0]?.href) {
    await page.goto(`${PROD}${featured[0].href}`, { waitUntil: "domcontentloaded", timeout: 120000 });
    const detailText = await page.locator("body").innerText();
    gate("FEATURED_DETAIL_PAGE", /group|package|seat|departure/i.test(detailText), { url: page.url() });
    report.samples.featured_detail = { card: featured[0], url: page.url() };
  }

  report.links = await crawlLinks(page);
  const broken = report.links.filter((l) => l.http_status >= 400 || l.legacy_leakage);
  gate("BROKEN_PUBLIC_LINKS_ZERO", broken.length === 0, { broken_count: broken.length, broken });

  const freshN = 20;
  const fresh = [];
  for (let i = 0; i < freshN; i += 1) fresh.push(await freshHomeSample(i, browser));
  const navs = fresh.map((f) => f.navMs);
  const fcps = fresh.map((f) => f.fcp).filter((n) => n != null);
  report.samples.fresh_homepage = {
    n: freshN,
    ttfb_p50_ms: pct(navs, 50),
    ttfb_p95_ms: pct(navs, 95),
    fcp_p95_ms: pct(fcps, 95),
    blank_15s: navs.filter((n) => n > 15000).length,
    rows: fresh,
  };
  gate("FRESH_HOMEPAGE_NO_BLANK_15S", report.samples.fresh_homepage.blank_15s === 0, report.samples.fresh_homepage);

  await page.goto(`${PROD}/login`, { waitUntil: "domcontentloaded" });
  const custReg = await page.locator('a[href*="/register"]').count();
  const agentReg = await page.locator('a[href*="/agent/register"]').count();
  gate("LOGIN_CUSTOMER_REGISTER_ROUTE", custReg > 0, { count: custReg });
  gate("LOGIN_AGENT_REGISTER_ROUTE", agentReg > 0, { count: agentReg });

  await page.goto(`${PROD}/register`, { waitUntil: "domcontentloaded" });
  const existingPrompts = await page.locator('text=/already have an account|sign in/i').count();
  gate("CUSTOMER_REGISTER_DUPLICATION_ZERO", existingPrompts <= 1, { existingPrompts });

  await page.goto(`${PROD}/agent/register`, { waitUntil: "domcontentloaded" });
  const licenseField = await page.locator('input[name="license_number"], input[name="licenseNumber"]').count();
  gate("AGENT_LICENSE_FIELD_VISIBLE", licenseField > 0, { count: licenseField });

  await page.goto(`${PROD}/groups`, { waitUntil: "domcontentloaded" });
  gate("GROUPS_UAT", page.url().includes("/groups"), { url: page.url() });
  await page.goto(`${PROD}/support`, { waitUntil: "domcontentloaded" });
  gate("SUPPORT_UAT", page.url().includes("/support"), { url: page.url() });

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
