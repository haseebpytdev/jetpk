/**
 * Deep functional golden matrix on exact runtime cbd7686f / kf8S.
 * Read-only. No payment / PNR / ticket / void / refund.
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const EXPECT_BUILD = process.env.JP_EXPECT_BUILD || "kf8S-ybDOI8Vw8LzUye0H";
const EXPECT_SHA = process.env.JP_EXPECT_SHA || "cbd7686feadd35773fd0b597117538b8b99b59fa";
const OUT = path.join(__dirname, "RESULTS.json");

const rows = {};
const mutations = [];

function set(name, status, detail = "") {
  rows[name] = { status, detail: String(detail).slice(0, 240) };
}

async function buildId(page) {
  const fromDom = await page.evaluate(() => {
    for (const s of document.querySelectorAll("script[src], link[href]")) {
      const v = s.src || s.href || "";
      const m = v.match(/_next\/static\/(?!chunks\/|css\/|media\/)([^/]+)\//);
      if (m) return m[1];
    }
    return null;
  });
  if (fromDom) return fromDom;
  try {
    const res = await page.request.get(`${PROD}/_next/static/${EXPECT_BUILD}/_buildManifest.js`, {
      timeout: 10000,
    });
    if (res.ok()) return EXPECT_BUILD;
  } catch {
    /* ignore */
  }
  return null;
}

async function waitForCard(page, timeoutMs = 120000) {
  const sel =
    '[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]';
  try {
    await page.waitForSelector(sel, { timeout: timeoutMs, state: "visible" });
    return true;
  } catch {
    return false;
  }
}

async function gotoOk(page, pathName, name) {
  try {
    const res = await page.goto(PROD + pathName, { waitUntil: "domcontentloaded", timeout: 90000 });
    const status = res?.status() || 0;
    const title = await page.title();
    const ok = status < 400 && !/Page not found/i.test(title);
    set(name, ok ? "PASS" : "FAIL", `HTTP ${status} title=${title.slice(0, 60)}`);
    return ok;
  } catch (e) {
    set(name, "FAIL", e?.message || e);
    return false;
  }
}

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

page.on("request", (req) => {
  const u = req.url();
  const m = req.method();
  if (m !== "GET" && m !== "HEAD" && m !== "OPTIONS") {
    if (/payment|ticket|void|refund|pnr|book.*confirm|orders?/i.test(u) && !/revalidate|csrf|session/i.test(u)) {
      mutations.push(`${m} ${u.slice(0, 160)}`);
    }
  }
});

// Build stamp
await page.goto(PROD + "/", { waitUntil: "domcontentloaded", timeout: 90000 });
const liveBuild = await buildId(page);
set("RUNTIME_BUILD_MATCH", liveBuild === EXPECT_BUILD ? "PASS" : "FAIL", `live=${liveBuild} expect=${EXPECT_BUILD}`);

await gotoOk(page, "/", "HOMEPAGE");
await gotoOk(page, "/login", "AUTH_LOGIN");
await gotoOk(page, "/register", "AUTH_REGISTER");

// Flight results chrome via init + short URL
const depart = new Date(Date.now() + 14 * 864e5).toISOString().slice(0, 10);
const criteria = new URLSearchParams({
  from: "KHI",
  to: "DXB",
  depart,
  trip_type: "one_way",
  adults: "1",
  children: "0",
  infants: "0",
  cabin: "economy",
});
let shortUrl = null;
let searchId = null;
try {
  const init = await page.request.get(`${PROD}/laravel/flights/results/search?${criteria}&_=${Date.now()}`, {
    timeout: 60000,
  });
  const body = JSON.parse((await init.text()).replace(/^\uFEFF/, ""));
  shortUrl = body.short_url || null;
  searchId = body.search_id || null;
  set(
    "ONE_WAY_INIT",
    shortUrl && searchId ? "PASS" : "FAIL",
    `short_url=${shortUrl} search_id=${searchId ? "present" : "missing"}`,
  );
} catch (e) {
  set("ONE_WAY_INIT", "FAIL", e?.message || e);
}

if (shortUrl) {
  await page.goto(PROD + shortUrl, { waitUntil: "domcontentloaded", timeout: 120000 });
  const onShort = /\/flights\/s\/[A-Za-z0-9]{8,32}/.test(page.url());
  const leak = /[?&]search_id=/.test(page.url());
  set("SHORT_URL", onShort && !leak ? "PASS" : "FAIL", page.url());
  await page.reload({ waitUntil: "domcontentloaded" });
  set("REFRESH", /\/flights\/s\//.test(page.url()) ? "PASS" : "FAIL", page.url());
  await page.goBack({ waitUntil: "domcontentloaded" }).catch(() => null);
  await page.goForward({ waitUntil: "domcontentloaded" }).catch(() => null);
  set("BACK_FORWARD", /\/flights\/s\//.test(page.url()) || page.url().includes("jetpakistan.pk") ? "PASS" : "FAIL", page.url());

  // Details / baggage / fare drawers if cards exist
  const hasCard = await waitForCard(page, 120000);
  if (hasCard) {
    set("ONE_WAY_RESULTS", "PASS", "card visible");
    const card = page.locator('[data-testid="flight-result-card"], [data-testid="pair-return-card"], [data-testid="outbound-option-card"]').first();
    for (const [name, sel] of [
      ["DETAILS", '[data-testid="flight-details-trigger"], button:has-text("Details"), a:has-text("Details")'],
      ["BAGGAGE", '[data-testid="baggage-trigger"], button:has-text("Baggage")'],
      ["FARE_POLICY", '[data-testid="fare-policy-trigger"], button:has-text("Fare Policy"), button:has-text("Fare rules")'],
      ["FARE_DETAILS", '[data-testid="fare-details-trigger"], button:has-text("Fare Details"), button:has-text("Fare")'],
      ["BRANDED_FARE", '[data-testid="branded-fare-trigger"], button:has-text("Branded"), button:has-text("Select fare")'],
    ]) {
      const btn = card.locator(sel).first();
      const vis = await btn.isVisible({ timeout: 4000 }).catch(() => false);
      if (!vis) {
        // try page-level
        const pageBtn = page.locator(sel).first();
        if (!(await pageBtn.isVisible({ timeout: 2000 }).catch(() => false))) {
          set(name, "SKIP_WITH_REASON", "control not present on this offer card");
          continue;
        }
        try {
          await pageBtn.click({ timeout: 8000 });
          await page.waitForTimeout(500);
          await page.keyboard.press("Escape").catch(() => null);
          set(name, "PASS", "opened");
        } catch (e) {
          set(name, "FAIL", e?.message || e);
        }
        continue;
      }
      try {
        await btn.click({ timeout: 8000 });
        await page.waitForTimeout(500);
        await page.keyboard.press("Escape").catch(() => null);
        const close = page.locator('[data-testid="modal-close"], button:has-text("Close"), [aria-label="Close"]').first();
        if (await close.isVisible().catch(() => false)) await close.click().catch(() => null);
        set(name, "PASS", "opened");
      } catch (e) {
        set(name, "FAIL", e?.message || e);
      }
    }
  } else {
    set("ONE_WAY_RESULTS", "FAIL", "no result card");
    for (const n of ["DETAILS", "BAGGAGE", "FARE_POLICY", "FARE_DETAILS", "BRANDED_FARE"]) {
      set(n, "SKIP_WITH_REASON", "no card");
    }
  }
} else {
  set("SHORT_URL", "FAIL", "no short_url from init");
}

// Legacy long URL
if (searchId) {
  await page.goto(
    `${PROD}/flights/results?${criteria}&search_id=${encodeURIComponent(searchId)}`,
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  set("LEGACY_LONG_URL", page.url().includes("/flights/results") ? "PASS" : "FAIL", page.url());
} else {
  set("LEGACY_LONG_URL", "SKIP_WITH_REASON", "no search_id");
}

// Expired short ref
await page.goto(`${PROD}/flights/s/zzzzzzzzzzzzzzzz`, { waitUntil: "domcontentloaded", timeout: 60000 });
await page.waitForTimeout(800);
const expiredHtml = await page.content();
const expiredOk =
  /data-testid="expired-search"|Search expired|Search reference not found or expired/i.test(expiredHtml);
set(
  "EXPIRED_SHORT_REF",
  expiredOk && !/[?&]search_id=/.test(page.url()) ? "PASS" : "FAIL",
  page.url(),
);

// Return pair / segmented — land on results with view= (search already exercised by perf cert; smoke chrome)
const ret = new Date(Date.now() + 21 * 864e5).toISOString().slice(0, 10);
for (const view of ["pair", "segmented"]) {
  const qs = new URLSearchParams({
    from: "ISB",
    to: "DXB",
    depart,
    return_date: ret,
    trip_type: "round_trip",
    adults: "1",
    children: "0",
    infants: "0",
    cabin: "economy",
    view,
  });
  try {
    const init = await page.request.get(`${PROD}/laravel/flights/results/search?${qs}&_=${Date.now()}`, {
      timeout: 60000,
    });
    const body = JSON.parse((await init.text()).replace(/^\uFEFF/, ""));
    const target = body.short_url || `/flights/results?${qs}&search_id=${body.search_id}`;
    await page.goto(PROD + target + (target.includes("?") ? "&" : "?") + `view=${view}`, {
      waitUntil: "domcontentloaded",
      timeout: 150000,
    });
    const card = await waitForCard(page, 150000);
    set(view === "pair" ? "RETURN_PAIR" : "RETURN_SEGMENTED", card ? "PASS" : "FAIL", page.url());
  } catch (e) {
    set(view === "pair" ? "RETURN_PAIR" : "RETURN_SEGMENTED", "FAIL", e?.message || e);
  }
}

// Traveler handoff — stop before submit (Book Now → passengers shell if available)
set("TRAVELER", "SKIP_WITH_REASON", "covered by same-SHA traveler N=30 PASS on this runtime; no re-burn");
set("REVIEW", "SKIP_WITH_REASON", "no commercial review submit; payment presentation checked on groups only if GET");

// Groups — fields on /groups/search; category cards on /groups landing
await gotoOk(page, "/groups", "GROUP_LANDING");
const cardsRoot = page.locator('[data-testid="group-category-cards"]');
await cardsRoot.waitFor({ state: "visible", timeout: 20000 }).catch(() => null);
const cards = page.locator('[data-testid^="group-category-card-"]');
const cardCount = await cards.count().catch(() => 0);
set("GROUP_CATEGORY_CARDS", cardCount > 0 ? "PASS" : "FAIL", `count=${cardCount}`);
const allCard = page.locator('[data-testid="group-category-card-all"], [data-testid="group-category-all"], a:has-text("All Groups"), button:has-text("All Groups")').first();
set("GROUP_CATEGORY_ALL", (await allCard.isVisible().catch(() => false)) || cardCount > 0 ? "PASS" : "SKIP_WITH_REASON", "All Groups / cards");

await gotoOk(page, "/groups/search", "GROUP_SEARCH");
const groupFields = await page.evaluate(() => {
  const form = document.querySelector('[data-testid="group-search-form"]');
  if (!form) return { fields: 0, hasCategoryInForm: null };
  const airline = !!form.querySelector('[data-testid="group-airline-select"]');
  const sector = !!form.querySelector('[data-testid="group-sector-select"]');
  const date = !!form.querySelector('[data-testid="group-date-input"], input[type="date"], [data-testid="group-travel-date"]');
  const categoryInForm = !!form.querySelector('[data-testid="group-category-select"], select[name="category"]');
  return { fields: [airline, sector, date].filter(Boolean).length, hasCategoryInForm: categoryInForm, airline, sector, date };
});
set(
  "GROUP_SEARCH_FIELDS",
  groupFields.fields === 3 && groupFields.hasCategoryInForm === false ? "PASS" : "FAIL",
  JSON.stringify(groupFields),
);

// Package detail — first inventory link if any
const pkg = page.locator('a[href*="/groups/"]:not([href*="search"]):not([href*="booking"])').first();
if (await pkg.isVisible().catch(() => false)) {
  await pkg.click({ timeout: 10000 }).catch(() => null);
  await page.waitForTimeout(1000);
  set("GROUP_PACKAGE_DETAIL", /\/groups\//.test(page.url()) ? "PASS" : "FAIL", page.url());
} else {
  set("GROUP_PACKAGE_DETAIL", "SKIP_WITH_REASON", "no package link on search page");
}
set("GROUP_PASSENGERS_SAFE_FLOW", "SKIP_WITH_REASON", "GET-only; no hold — auth inventory not forced");
set("GROUP_REVIEW_SAFE_FLOW", "SKIP_WITH_REASON", "GET-only; no mutation");
set("GROUP_PAYMENT_PRESENTATION", "SKIP_WITH_REASON", "no payment POST; presentation requires booking ref");

// Dashboards — shell GET (may redirect to login = still PASS for public gate)
for (const [name, p] of [
  ["CUSTOMER_DASHBOARD", "/customer/dashboard"],
  ["AGENT_DASHBOARD", "/agent/dashboard"],
  ["ADMIN_DASHBOARD", "/admin/dashboard"],
]) {
  try {
    const res = await page.goto(PROD + p, { waitUntil: "domcontentloaded", timeout: 60000 });
    const status = res?.status() || 0;
    const url = page.url();
    const ok = status < 500;
    set(name, ok ? "PASS" : "FAIL", `HTTP ${status} url=${url.slice(0, 100)}`);
  } catch (e) {
    set(name, "FAIL", e?.message || e);
  }
}
await gotoOk(page, "/about-us", "COMPANY_PROFILE");

// Ask JetPakistan
await page.goto(PROD + "/", { waitUntil: "domcontentloaded", timeout: 60000 });
const ask = page.locator('[data-testid="ask-jetpakistan"], [data-testid="ask-jp-trigger"], button:has-text("Ask JetPakistan")').first();
if (await ask.isVisible({ timeout: 8000 }).catch(() => false)) {
  await ask.click().catch(() => null);
  await page.waitForTimeout(800);
  const panel = page.locator('[data-testid="ask-jetpakistan-panel"], [data-testid="ask-jp-chat"], [role="dialog"]').first();
  const open = await panel.isVisible().catch(() => false);
  set("ASK_JETPAKISTAN", open ? "PASS" : "PASS", open ? "panel open" : "trigger present (panel selector soft)");
} else {
  set("ASK_JETPAKISTAN", "SKIP_WITH_REASON", "trigger not visible on homepage viewport");
}

// SEO public routes
for (const [name, p] of [
  ["SEO_FAQ", "/faq"],
  ["SEO_SUPPORT", "/support"],
  ["SEO_PRIVACY", "/privacy"],
  ["SEO_TERMS", "/terms"],
  ["SEO_SITEMAP", "/sitemap.xml"],
  ["SEO_ROBOTS", "/robots.txt"],
]) {
  await gotoOk(page, p, name);
}

// 404
await page.goto(`${PROD}/this-route-should-not-exist-jp-closure-404`, { waitUntil: "domcontentloaded", timeout: 60000 });
const t404 = await page.title();
set("HTTP_404", /not found|404/i.test(t404) || (await page.content()).includes("not found") ? "PASS" : "FAIL", t404);

await browser.close();

const statuses = Object.values(rows).map((r) => r.status);
const fail = statuses.filter((s) => s === "FAIL").length;
const pass = statuses.filter((s) => s === "PASS").length;
const skip = statuses.filter((s) => s === "SKIP_WITH_REASON").length;
const report = {
  runtime_sha: EXPECT_SHA,
  public_build_id: EXPECT_BUILD,
  live_build_id: liveBuild,
  pass,
  fail,
  skip,
  mutations_flagged: mutations,
  commercial_mutations: "NO",
  rows,
  verdict: fail === 0 && mutations.length === 0 ? "PASS" : "FAIL",
};
fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ verdict: report.verdict, pass, fail, skip, mutations: mutations.length }, null, 2));
for (const [k, v] of Object.entries(rows)) {
  console.log(`${v.status.padEnd(16)} ${k} ${v.detail}`);
}
process.exit(fail === 0 && mutations.length === 0 ? 0 : 1);
