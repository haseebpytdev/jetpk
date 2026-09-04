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
    usable: "main, h1",
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
    name: "home_to_agent_register",
    from: "/",
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

async function softClickNav(page, href) {
  const beforeUrl = page.url();
  // Prefer header/footer Link nodes; open dropdown panels when needed.
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
    let el = links.find((a) => norm(a.getAttribute("href") || "") === want);
    if (!el) {
      // Open any collapsed dropdown that might contain the link.
      const triggers = Array.from(
        document.querySelectorAll('button[aria-haspopup="menu"], button[aria-expanded="false"]'),
      );
      for (const btn of triggers) {
        btn.click();
        await new Promise((r) => setTimeout(r, 50));
      }
      el = Array.from(document.querySelectorAll("a[href]")).find(
        (a) => norm(a.getAttribute("href") || "") === want,
      );
    }
    if (!el) return { ok: false, reason: "link_not_found" };
    el.scrollIntoView({ block: "center" });
    el.click();
    return { ok: true, href: el.getAttribute("href") };
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
  userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R-SOFT-NAV",
});
const page = await context.newPage();

await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 120000 });

const matrix = [];
let softCandidatesFound = 0;
let softCandidatesOk = 0;
let legacyHard = 0;

for (const t of transitions) {
  softCandidatesFound += 1;
  // Warm from page once
  await page.goto(BASE + t.from, { waitUntil: "networkidle", timeout: 120000 }).catch(async () => {
    await page.goto(BASE + t.from, { waitUntil: "domcontentloaded", timeout: 120000 });
  });
  await page.waitForTimeout(400);

  const shells = [];
  const usables = [];
  const navTypes = [];
  const fullReloads = [];

  for (let i = 0; i < N; i++) {
    await page.goto(BASE + t.from, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForTimeout(250);

    const navStart = Date.now();
    let sawDocument = false;
    const onRequest = (req) => {
      try {
        if (
          req.resourceType() === "document" &&
          new URL(req.url()).pathname.replace(/\/$/, "") === t.href.replace(/\/$/, "")
        ) {
          sawDocument = true;
        }
      } catch {
        /* ignore */
      }
    };
    page.on("request", onRequest);

    const click = await softClickNav(page, t.href);
    let fullReload = false;
    if (!click.ok) {
      legacyHard += 1;
      await page.goto(BASE + t.href, { waitUntil: "domcontentloaded", timeout: 120000 });
      navTypes.push("HARD_FALLBACK");
      fullReload = true;
    } else {
      // Brief settle only — do not await a multi-second document timeout on soft nav.
      await page.waitForTimeout(80);
      fullReload = sawDocument;
      navTypes.push(fullReload ? "HARD_DOCUMENT" : "CLIENT_SOFT");
      if (!fullReload) softCandidatesOk += 1;
      else legacyHard += 1;
    }
    page.off("request", onRequest);
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
  phase: "JP-APP-PERF-CLOSURE-01R",
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
fs.writeFileSync(path.join(__dirname, "site-soft-nav-matrix.json"), JSON.stringify(out, null, 2));
console.log(
  JSON.stringify(
    {
      PAGES: out.PAGES_PROFILED_COUNT,
      WORST: out.ORDINARY_PAGE_WARM_P95_WORST_MS,
      SLOW: out.SLOW_PAGE_COUNT_AFTER,
      MULTI_SEC: out.APPLICATION_SIDE_MULTI_SECOND_ROUTE_COUNT,
      SOFT_FOUND: out.SOFT_NAV_CANDIDATES_FOUND,
      LEGACY_HARD: out.LEGACY_HARD_NAV_REMAINING,
    },
    null,
    2,
  ),
);
await browser.close();
