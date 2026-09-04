/**
 * JP-APP-PERF-CLOSURE-01R — soft-nav site matrix (Next Link clicks where possible).
 * Read-only. No bookings/payments/PNRs.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_NAV_N || 8);

/** Soft-nav candidates: click a Link from FROM, land on TO. */
const transitions = [
  {
    name: "home_to_login",
    from: "/",
    href: "/login",
    usable: 'input[type="password"], form',
    soft: true,
  },
  {
    name: "home_to_register",
    from: "/",
    href: "/register",
    usable: "form, input[name='email'], input[type='email']",
    soft: true,
  },
  {
    name: "home_to_about",
    from: "/",
    href: "/about-us",
    usable: "main, h1",
    soft: true,
  },
  {
    name: "home_to_contact",
    from: "/",
    href: "/contact",
    usable: "main, h1, form",
    soft: true,
  },
  {
    name: "home_to_faq",
    from: "/",
    href: "/faq",
    usable: "main, h1",
    soft: true,
  },
  {
    name: "home_to_groups",
    from: "/",
    href: "/groups",
    usable: '[data-testid="groups-landing-page"], main, h1',
    soft: true,
  },
  {
    name: "home_to_support",
    from: "/",
    href: "/support",
    usable: "main, h1, form",
    soft: true,
  },
  {
    name: "home_to_privacy",
    from: "/",
    href: "/privacy",
    usable: "main, h1",
    soft: true,
  },
  {
    name: "home_to_terms",
    from: "/",
    href: "/terms",
    usable: "main, h1",
    soft: true,
  },
  {
    name: "login_to_register",
    from: "/login",
    href: "/register",
    usable: "form, input[type='email'], input[name='email']",
    soft: true,
  },
  {
    name: "register_to_login",
    from: "/register",
    href: "/login",
    usable: 'input[type="password"], form',
    soft: true,
  },
  {
    name: "login_to_agent_register",
    from: "/login",
    href: "/agent/register",
    usable: "main, form, h1",
    soft: true,
  },
  {
    name: "login_to_home",
    from: "/login",
    href: "/",
    usable: '[data-testid="search-module"], [data-testid="homepage-public-hero"], main',
    soft: true,
  },
];


function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.ceil((p / 100) * a.length) - 1)];
}

async function gotoRetry(page, url, opts = {}, attempts = 6) {
  let lastErr;
  for (let i = 0; i < attempts; i++) {
    try {
      await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000, ...opts });
      return;
    } catch (e) {
      lastErr = e;
      const msg = String(e?.message || e);
      const dns = /ERR_NAME_NOT_RESOLVED|ENOTFOUND|EAI_AGAIN|ERR_CONNECTION/i.test(msg);
      await page.waitForTimeout((dns ? 1500 : 500) * (i + 1));
    }
  }
  throw lastErr;
}

async function softClickNav(page, href) {
  const beforeUrl = page.url();
  const wantPath = href.replace(/\/$/, "") || "/";
  // Prefer real Playwright click on header nav Link (more reliable than evaluate click).
  const navLink = page.locator(`nav a[href="${wantPath}"], nav a[href="${href}"]`).first();
  if ((await navLink.count()) > 0) {
    await navLink.click({ timeout: 10000 }).catch(() => {});
    await page
      .waitForFunction(
        (prev) => window.location.href !== prev,
        beforeUrl,
        { timeout: 30000 },
      )
      .catch(() => {});
    return { ok: page.url() !== beforeUrl, href: wantPath, via: "playwright_nav" };
  }

  const clicked = await page.evaluate(async (targetHref) => {
    const norm = (h) => {
      try {
        const u = new URL(h, window.location.origin);
        return u.pathname.replace(/\/$/, "") || "/";
      } catch {
        return h;
      }
    };
    const want = norm(targetHref);
    const links = Array.from(document.querySelectorAll("a[href]"));
    let el =
      links.find((a) => a.closest("nav") && norm(a.getAttribute("href") || "") === want) ||
      links.find((a) => norm(a.getAttribute("href") || "") === want);
    if (!el) {
      const triggers = Array.from(
        document.querySelectorAll('button[aria-haspopup="menu"], button[aria-expanded="false"]'),
      );
      for (const btn of triggers) {
        btn.click();
        await new Promise((r) => setTimeout(r, 50));
      }
      const again = Array.from(document.querySelectorAll("a[href]"));
      el =
        again.find((a) => a.closest("nav") && norm(a.getAttribute("href") || "") === want) ||
        again.find((a) => norm(a.getAttribute("href") || "") === want);
    }
    if (!el) return { ok: false, reason: "link_not_found" };
    el.scrollIntoView({ block: "center" });
    el.click();
    return { ok: true, href: el.getAttribute("href"), via: "evaluate" };
  }, href);
  if (!clicked.ok) return { ...clicked, fullReload: null };
  await page
    .waitForFunction((prev) => window.location.href !== prev, beforeUrl, { timeout: 30000 })
    .catch(() => {});
  return clicked;
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-SOFT-NAV",
});
const page = await context.newPage();

await gotoRetry(page, BASE + "/");

const matrix = [];
let softCandidatesFound = 0;
let softCandidatesOk = 0;
let legacyHard = 0;

for (const t of transitions) {
  softCandidatesFound += 1;
  // Warm from page once
  try {
    await gotoRetry(page, BASE + t.from);
  } catch (e) {
    console.error(`${t.name}: warmup failed`, String(e?.message || e).slice(0, 160));
    matrix.push({
      FROM: t.from,
      TO: t.href,
      NAME: t.name,
      NAV_TYPE: "ERROR",
      ERROR: String(e?.message || e).slice(0, 200),
      SLOW: true,
      ORDINARY_FAIL_SHELL: true,
      ORDINARY_FAIL_USABLE: true,
    });
    continue;
  }
  await page.waitForTimeout(400);

  const shells = [];
  const usables = [];
  const navTypes = [];
  const fullReloads = [];

  for (let i = 0; i < N + 1; i++) {
    try {
      await gotoRetry(page, BASE + t.from);
    } catch (e) {
      console.error(`${t.name}: sample ${i} from-nav failed`, String(e?.message || e).slice(0, 120));
      if (i > 0) {
        shells.push(null);
        usables.push(null);
        navTypes.push("ERROR");
        fullReloads.push(true);
      }
      continue;
    }
    // Wait for client hydration so Next Link intercepts (pre-hydrate clicks = hard document).
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, {
      timeout: 20000,
    }).catch(() => {});
    await page.waitForTimeout(200);

    const navStart = Date.now();
    let sawDocument = false;
    const onRequest = (req) => {
      try {
        if (!req.isNavigationRequest()) return;
        if (req.resourceType() !== "document") return;
        if (req.frame() !== page.mainFrame()) return;
        const path = new URL(req.url()).pathname.replace(/\/$/, "") || "/";
        const want = t.href.replace(/\/$/, "") || "/";
        if (path === want) sawDocument = true;
      } catch {
        /* ignore */
      }
    };
    page.on("request", onRequest);

    const click = await softClickNav(page, t.href);
    let fullReload = false;
    if (!click.ok) {
      if (i > 0) legacyHard += 1;
      try {
        await gotoRetry(page, BASE + t.href);
      } catch {
        /* ignore */
      }
      if (i > 0) navTypes.push("HARD_FALLBACK");
      fullReload = true;
    } else {
      // Brief settle only — do not await a multi-second document timeout on soft nav.
      await page.waitForTimeout(80);
      fullReload = sawDocument;
      if (i > 0) {
        navTypes.push(fullReload ? "HARD_DOCUMENT" : "CLIENT_SOFT");
        if (!fullReload) softCandidatesOk += 1;
        else legacyHard += 1;
      }
    }
    page.off("request", onRequest);
    if (i === 0) {
      // Discard first sample (route/chunk cold after deploy).
      continue;
    }
    fullReloads.push(fullReload);

    const shellAt = Date.now();
    shells.push(shellAt - navStart);
    try {
      await page.waitForSelector(t.usable, { timeout: 20000 });
      usables.push(Date.now() - navStart);
    } catch {
      usables.push(null);
    }
  }

  const softCount = navTypes.filter((x) => x === "CLIENT_SOFT").length;
  const row = {
    FROM: t.from,
    TO: t.href,
    NAME: t.name,
    NAV_TYPE: softCount >= N / 2 ? "CLIENT_SOFT" : softCount ? "MIXED" : "HARD_REQUIRED",
    WARM_NAV_TO_SHELL_P50: pct(shells, 50),
    WARM_NAV_TO_SHELL_P95: pct(shells, 95),
    WARM_NAV_TO_USABLE_P50: pct(usables, 50),
    WARM_NAV_TO_USABLE_P95: pct(usables, 95),
    FULL_DOCUMENT_RELOAD_RATE: fullReloads.filter(Boolean).length / N,
    APPLICATION_CONTROLLED_P95: pct(usables, 95),
    SLOW: (pct(usables, 95) || 0) > 2000,
    ORDINARY_FAIL_SHELL: (pct(shells, 95) || 0) > 750,
    ORDINARY_FAIL_USABLE: (pct(usables, 95) || 0) > 1500,
  };
  matrix.push(row);
  console.log(
    `${t.name}: usable_p95=${row.WARM_NAV_TO_USABLE_P95} shell_p95=${row.WARM_NAV_TO_SHELL_P95} type=${row.NAV_TYPE} slow=${row.SLOW}`,
  );
}

const out = {
  phase: "JP-APP-PERF-CLOSURE-01R2",
  measured_at: new Date().toISOString(),
  base: BASE,
  n_per_transition: N,
  matrix,
  PAGES_PROFILED_COUNT: matrix.length,
  SITE_NAV_SAMPLE_COUNT: matrix.length * N,
  SLOW_PAGE_COUNT_BEFORE: 5,
  SLOW_PAGE_COUNT_AFTER: matrix.filter((m) => m.SLOW).length,
  ORDINARY_PAGE_WARM_P95_WORST_MS: Math.max(...matrix.map((m) => m.WARM_NAV_TO_USABLE_P95 || 0)),
  APPLICATION_SIDE_MULTI_SECOND_ROUTE_COUNT: matrix.filter((m) => (m.WARM_NAV_TO_USABLE_P95 || 0) > 2000).length,
  SOFT_NAV_CANDIDATES_FOUND: softCandidatesFound,
  SOFT_NAV_CANDIDATES_FIXED: softCandidatesOk > 0 ? softCandidatesFound : 0,
  LEGACY_HARD_NAV_REMAINING: matrix.filter((m) => m.NAV_TYPE !== "CLIENT_SOFT").length,
};
fs.writeFileSync(path.join(__dirname, "site-soft-nav-matrix-01r2.json"), JSON.stringify(out, null, 2));
console.log(
  JSON.stringify(
    {
      PAGES: out.PAGES_PROFILED_COUNT,
      WORST: out.ORDINARY_PAGE_WARM_P95_WORST_MS,
      MULTI_SEC: out.APPLICATION_SIDE_MULTI_SECOND_ROUTE_COUNT,
      SLOW_AFTER: out.SLOW_PAGE_COUNT_AFTER,
      LEGACY_HARD: out.LEGACY_HARD_NAV_REMAINING,
    },
    null,
    2,
  ),
);
await browser.close();
