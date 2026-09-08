/**
 * Closure-05 production postdeploy certification (read-only browser + API).
 */
import { chromium, devices } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const OUT = path.join(__dirname, "production-browser-cert.json");
const SCREEN = path.join(__dirname, "screenshots");

const report = {
  captured_at: new Date().toISOString(),
  environment: "production",
  engineering_sha: "f039bef3dda1320c08fdccb4633d5c7c34b3b61e",
  gates: {},
  network: [],
  console: [],
  screenshots: {},
};

async function shot(page, file) {
  try {
    await page.screenshot({ path: file, fullPage: false, timeout: 120000, animations: "disabled" });
    return true;
  } catch (e) {
    report.console.push({ type: "warning", text: `screenshot_fail:${file}:${String(e?.message || e).slice(0, 120)}` });
    return false;
  }
}

async function safeNav(page, action, label) {
  try {
    await action();
    return true;
  } catch (e) {
    report.console.push({ type: "warning", text: `${label}:${String(e?.message || e).slice(0, 160)}` });
    return false;
  }
}

function gate(name, pass, detail = {}) {
  report.gates[name] = { pass, ...detail };
}

async function main() {
  fs.mkdirSync(SCREEN, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({
    ignoreHTTPSErrors: true,
    serviceWorkers: "block",
  });
  await ctx.route("**/*", (route) => {
    const type = route.request().resourceType();
    if (type === "font" || type === "media") return route.abort();
    return route.continue();
  });
  const page = await ctx.newPage();
  page.setDefaultNavigationTimeout(90000);
  page.setDefaultTimeout(45000);
  const request = ctx.request;

  page.on("console", (m) => {
    if (["error", "warning"].includes(m.type())) report.console.push({ type: m.type(), text: m.text().slice(0, 300) });
  });

  const homeApi = await request.get(`${PROD}/api/public/content/homepage`);
  const home = await homeApi.json();
  const featured = home?.featured_deals?.items ?? [];
  const unavailable = featured.filter((f) => f.availability === "unavailable" || !f.href);
  const legacyHrefs = featured.filter((f) => String(f.href ?? "").includes("/groups/package/"));
  const nextHrefs = featured.filter((f) => String(f.href ?? "").startsWith("/groups/") && !String(f.href).includes("/package/"));

  gate("PRODUCTION_HOMEPAGE_API", homeApi.ok(), { status: homeApi.status() });
  gate("FEATURED_NEXT_HREFS", nextHrefs.length > 0 && legacyHrefs.length === 0, {
    next_count: nextHrefs.length,
    legacy_count: legacyHrefs.length,
    sample_href: nextHrefs[0]?.href ?? null,
  });
  gate("UNAVAILABLE_FEATURED_COUNT_ZERO", unavailable.length === 0, { unavailable_count: unavailable.length });
  gate("SUPPORT_CTA_PRESENT", Boolean(home?.support_cta?.enabled), {
    support_image: home?.support_cta?.image ? "present" : "fallback",
  });

  const staleLabels = [
    ...(home?.routes?.items ?? []).map((r) => r.price_label),
    ...(home?.destinations?.items ?? []).map((d) => d.price_label),
  ].filter((l) => String(l).toLowerCase().includes("check fare"));
  gate("STALE_FARE_NEUTRAL_LABELS", staleLabels.length === 0, { check_fare_count: staleLabels.length });

  await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
  await shot(page, path.join(SCREEN, "prod-home-desktop.png"));
  report.screenshots.prod_home_desktop = "screenshots/prod-home-desktop.png";

  const groupsSearch = await page.goto(`${PROD}/groups/search`, { waitUntil: "domcontentloaded", timeout: 120000 });
  const groupsText = await page.locator("body").innerText();
  gate("GROUPS_SEARCH_NEXT_UI", groupsSearch?.ok() && /group|package|search/i.test(groupsText), { url: page.url() });
  await shot(page, path.join(SCREEN, "prod-groups-search-desktop.png"));

  if (nextHrefs[0]?.href) {
    const detail = await page.goto(`${PROD}${nextHrefs[0].href}`, { waitUntil: "domcontentloaded", timeout: 120000 });
    gate("FEATURED_DETAIL_NEXT_UI", detail?.ok() && page.url().includes("/groups/"), {
      url: page.url(),
      href: nextHrefs[0].href,
      public_id: nextHrefs[0].public_id ?? null,
    });
    const refreshOk = await safeNav(
      page,
      () => page.reload({ waitUntil: "commit", timeout: 90000 }),
      "detail_refresh",
    );
    const backOk = await safeNav(
      page,
      () => page.goBack({ waitUntil: "commit", timeout: 90000 }),
      "detail_back",
    );
    gate("FEATURED_DETAIL_REFRESH_BACK", refreshOk && backOk, { refreshOk, backOk });
    await shot(page, path.join(SCREEN, "prod-featured-detail-desktop.png"));
    report.screenshots.prod_featured_detail_desktop = "screenshots/prod-featured-detail-desktop.png";
  }

  await safeNav(page, () => page.goto(`${PROD}/groups`, { waitUntil: "domcontentloaded", timeout: 90000 }), "groups_index");
  gate("GROUPS_INDEX_DIRECT", page.url().includes("/groups"), { url: page.url() });
  await shot(page, path.join(SCREEN, "prod-groups-index-desktop.png"));
  report.screenshots.prod_groups_index_desktop = "screenshots/prod-groups-index-desktop.png";

  const legacySample = featured[0]?.public_id ?? featured[0]?.id;
  if (legacySample) {
    const legacyRes = await request.get(`${PROD}/groups/package/${legacySample}`, { maxRedirects: 0 });
    gate("LEGACY_PACKAGE_REDIRECT", legacyRes.status() === 301 || legacyRes.status() === 302 || legacyRes.status() === 308, {
      status: legacyRes.status(),
      location: legacyRes.headers()["location"] ?? null,
    });
  }

  const mobile = await browser.newContext({
    ...devices["iPhone 13"],
    ignoreHTTPSErrors: true,
    serviceWorkers: "block",
  });
  await mobile.route("**/*", (route) => {
    const type = route.request().resourceType();
    if (type === "font" || type === "media") return route.abort();
    return route.continue();
  });
  const mobilePage = await mobile.newPage();
  mobilePage.setDefaultNavigationTimeout(90000);
  await mobilePage.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
  await shot(mobilePage, path.join(SCREEN, "prod-home-mobile.png"));
  report.screenshots.prod_home_mobile = "screenshots/prod-home-mobile.png";
  await safeNav(
    mobilePage,
    () => mobilePage.goto(`${PROD}/groups/search`, { waitUntil: "domcontentloaded", timeout: 90000 }),
    "mobile_groups_search",
  );
  await shot(mobilePage, path.join(SCREEN, "prod-groups-search-mobile.png"));
  report.screenshots.prod_groups_search_mobile = "screenshots/prod-groups-search-mobile.png";
  await mobile.close();

  const hasLabel = (label) => Boolean(String(label ?? "").trim());
  const isZeroPriceLabel = (label) => /(?:^|\s)0(?:\s|$|,)/.test(String(label)) || /^PKR\s*0/i.test(String(label));
  const trendingDynamic = (home?.routes?.items ?? []).filter((r) => String(r.dynamic_fare_enabled) === "1");
  const trendingOk = trendingDynamic.every((r) => hasLabel(r.price_label) && !isZeroPriceLabel(r.price_label));
  const destOk = (home?.destinations?.items ?? []).every((d) => hasLabel(d.price_label) && !isZeroPriceLabel(d.price_label));
  gate("TRENDING_DESTINATION_LABELS_PRESENT", trendingOk && destOk, {
    trending_dynamic_count: trendingDynamic.length,
    trending_sample: trendingDynamic[0]?.price_label ?? null,
    destination_sample: home?.destinations?.items?.[0]?.price_label ?? null,
  });

  const allPass = Object.values(report.gates).every((g) => g.pass === true);
  report.CMS_PRODUCTION_BROWSER_CERT = allPass ? "PASS" : "PARTIAL";
  report.result = report.CMS_PRODUCTION_BROWSER_CERT;

  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  await browser.close();
  console.log(JSON.stringify({ result: report.result, gates: report.gates }, null, 2));
  process.exit(allPass ? 0 : 1);
}

main();
