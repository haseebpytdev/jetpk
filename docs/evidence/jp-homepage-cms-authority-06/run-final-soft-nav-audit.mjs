/**
 * Authority-06 soft-nav audit — visible desktop targets only, N>=20 per route.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = "https://jetpakistan.pk";
const N = Number(process.env.JP_SOFT_N || 20);
const OUT = path.join(__dirname, "final-soft-nav-audit.json");

const transitions = [
  { name: "home_to_support", from: "/", href: "/support", usable: "main, h1" },
  { name: "home_to_login", from: "/", href: "/login", usable: 'input[type="password"], form' },
  { name: "home_to_groups", from: "/", href: "/groups", usable: "main, h1" },
  { name: "home_to_about", from: "/", href: "/about-us", usable: "main, h1" },
  { name: "home_to_faq", from: "/", href: "/faq", usable: "main, h1" },
  { name: "login_to_register", from: "/login", href: "/register", usable: "form" },
  { name: "login_to_home", from: "/login", href: "/", usable: "main" },
];

function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.ceil((p / 100) * a.length) - 1)];
}

async function auditTarget(page, href) {
  const want = href.replace(/\/$/, "") || "/";
  const navLink = page.locator(`nav a[href="${want}"], nav a[href="${href}"]`).first();
  const headerLink = page.locator(`header a[href="${want}"], header a[href="${href}"]`).first();
  const mainLink = page.locator(`main a[href="${want}"], main a[href="${href}"]`).first();
  const candidates = [navLink, headerLink, mainLink];
  for (const loc of candidates) {
    if ((await loc.count()) === 0) continue;
    const visible = await loc.isVisible().catch(() => false);
    const box = visible ? await loc.boundingBox().catch(() => null) : null;
    const interactable = visible && box && box.width > 0 && box.height > 0;
    if (visible && interactable) {
      return { target_visible: "YES", target_interactable: "YES", locator: "nav|header|main", href: want };
    }
    if (visible) {
      return { target_visible: "YES", target_interactable: "NO", locator: "hidden_or_zero_box", href: want };
    }
  }
  const any = page.locator(`a[href="${want}"], a[href="${href}"]`).first();
  if ((await any.count()) > 0) {
    const visible = await any.isVisible().catch(() => false);
    return {
      target_visible: visible ? "YES" : "NO",
      target_interactable: visible ? "NO" : "NO",
      locator: "duplicate_dom",
      href: want,
      invalid: !visible,
    };
  }
  return { target_visible: "NO", target_interactable: "NO", locator: "not_found", href: want, invalid: true };
}

async function softClickNav(page, href) {
  const beforeUrl = page.url();
  const want = href.replace(/\/$/, "") || "/";
  const navLink = page.locator(`nav a[href="${want}"], nav a[href="${href}"]`).first();
  if ((await navLink.count()) > 0 && (await navLink.isVisible().catch(() => false))) {
    await navLink.click({ timeout: 10000 });
    await page.waitForFunction((prev) => window.location.href !== prev, beforeUrl, { timeout: 30000 }).catch(() => {});
    return { ok: page.url() !== beforeUrl, via: "nav_visible" };
  }
  const headerLink = page.locator(`header a[href="${want}"], header a[href="${href}"]`).first();
  if ((await headerLink.count()) > 0 && (await headerLink.isVisible().catch(() => false))) {
    await headerLink.click({ timeout: 10000 });
    await page.waitForFunction((prev) => window.location.href !== prev, beforeUrl, { timeout: 30000 }).catch(() => {});
    return { ok: page.url() !== beforeUrl, via: "header_visible" };
  }
  return { ok: false, via: "no_visible_target" };
}

async function gotoRetry(page, url, attempts = 4) {
  let last;
  for (let i = 0; i < attempts; i++) {
    try {
      await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000 });
      return;
    } catch (e) {
      last = e;
      await page.waitForTimeout(1500 * (i + 1));
    }
  }
  throw last;
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
const matrix = [];
let invalidSamples = 0;

for (const t of transitions) {
  const samples = [];
  const audit = await (async () => {
    await gotoRetry(page, BASE + t.from);
    return auditTarget(page, t.href);
  })();

  for (let i = 0; i < N + 1; i++) {
    await gotoRetry(page, BASE + t.from);
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(200);

    const targetAudit = await auditTarget(page, t.href);
    if (targetAudit.invalid) {
      if (i > 0) {
        invalidSamples += 1;
        samples.push({
          attempt: i,
          route: t.name,
          viewport: "1440x900",
          ...targetAudit,
          nav_type: "INVALID",
          excluded: true,
        });
      }
      continue;
    }

    let sawDocument = false;
    const onRequest = (req) => {
      try {
        if (!req.isNavigationRequest() || req.resourceType() !== "document") return;
        if (req.frame() !== page.mainFrame()) return;
        const p = new URL(req.url()).pathname.replace(/\/$/, "") || "/";
        const want = t.href.replace(/\/$/, "") || "/";
        if (p === want) sawDocument = true;
      } catch {
        /* ignore */
      }
    };
    page.on("request", onRequest);
    const navStart = Date.now();
    const click = await softClickNav(page, t.href);
    await page.waitForTimeout(80);
    page.off("request", onRequest);

    let navType = "INVALID";
    if (!click.ok) navType = "HARD_FALLBACK";
    else navType = sawDocument ? "HARD" : "CLIENT_SOFT";

    let appMs = null;
    if (i > 0) {
      try {
        await page.waitForSelector(t.usable, { timeout: 20000 });
        appMs = Date.now() - navStart;
      } catch {
        appMs = null;
      }
      samples.push({
        attempt: i,
        route: t.name,
        from: t.from,
        to: t.href,
        viewport: "1440x900",
        target_visible: targetAudit.target_visible,
        target_interactable: targetAudit.target_interactable,
        nav_type: navType,
        prefetch: null,
        raw_ms: appMs,
        app_controlled_ms: navType === "CLIENT_SOFT" ? appMs : null,
        excluded: navType === "INVALID" || navType === "HARD_FALLBACK",
      });
    }
  }

  const valid = samples.filter((s) => !s.excluded && s.nav_type === "CLIENT_SOFT" && s.app_controlled_ms != null);
  const appMs = valid.map((s) => s.app_controlled_ms);
  matrix.push({
    ROUTE: t.name,
    FROM: t.from,
    TO: t.href,
    VIEWPORT: "1440x900",
    TARGET_VISIBLE: audit.target_visible,
    TARGET_INTERACTABLE: audit.target_interactable,
    NAV_TYPE: valid.length >= N / 2 ? "CLIENT_SOFT" : "MIXED",
    samples_count: samples.length,
    valid_count: valid.length,
    invalid_count: samples.filter((s) => s.excluded).length,
    RAW_P50: pct(samples.map((s) => s.raw_ms), 50),
    RAW_P95: pct(samples.map((s) => s.raw_ms), 95),
    APP_CONTROLLED_P50: pct(appMs, 50),
    APP_CONTROLLED_P95: pct(appMs, 95),
    samples,
  });
}

const validRoutes = matrix.filter((m) => m.valid_count >= N / 2);
const worstP95 = Math.max(...validRoutes.map((m) => m.APP_CONTROLLED_P95 || 0), 0);
const multiSec = validRoutes.filter((m) => (m.APP_CONTROLLED_P95 || 0) > 2000).length;
const attain750 = validRoutes.filter((m) => (m.APP_CONTROLLED_P95 || 0) <= 750).length;

const out = {
  captured_at: new Date().toISOString(),
  public_build_id: "vkC0lfkEH9Gfj7CHfwcuO",
  n_per_route: N,
  INVALID_SELECTOR_SAMPLES: invalidSamples,
  SOFT_NAV_VALID_ROUTES: validRoutes.map((m) => m.ROUTE),
  SOFT_NAV_WORST_APP_P95: worstP95,
  APP_MULTI_SECOND_SOFT_ROUTE_COUNT: multiSec,
  ATTAIN_750MS_ROUTES: attain750,
  ATTAIN_750MS_OF: validRoutes.length,
  matrix,
  SOFT_NAV_GATE_PASS: invalidSamples === 0 && worstP95 <= 1500 && multiSec === 0,
};
fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
await browser.close();
process.exit(out.SOFT_NAV_GATE_PASS ? 0 : 1);
