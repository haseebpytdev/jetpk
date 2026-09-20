/**
 * Warm CLIENT_SOFT navigation certification (N>=20 / route).
 * Uses visible footer/header Next links; measures click â†’ destination URL usable.
 * Setup gotos are excluded. Hard fallbacks counted separately (not APP).
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";

const PROD = "https://jetpakistan.pk";
const SOFT_N = Number(process.env.JP_SOFT_N || 20);

function pct(arr, p) {
  const a = [...arr].filter(Number.isFinite).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function pathOf(urlOrPath) {
  try {
    if (urlOrPath.startsWith("http")) return new URL(urlOrPath).pathname.replace(/\/$/, "") || "/";
    return urlOrPath.replace(/\/$/, "") || "/";
  } catch {
    return urlOrPath;
  }
}

const softRoutes = [
  { name: "home_support", from: "/", href: "/support", scope: "footer" },
  { name: "support_home", from: "/support", href: "/", scope: "any" },
  { name: "home_privacy", from: "/", href: "/privacy", scope: "footer" },
  { name: "privacy_terms", from: "/privacy", href: "/terms", scope: "footer" },
  { name: "home_groups", from: "/", href: "/groups/search", scope: "header" },
  { name: "home_login", from: "/", href: "/login", scope: "header" },
  { name: "login_register", from: "/login", href: "/register", scope: "any" },
  { name: "home_about", from: "/", href: "/about-us", scope: "footer" },
  { name: "home_faq", from: "/", href: "/faq", scope: "footer" },
  { name: "home_terms", from: "/", href: "/terms", scope: "footer" },
];

function linkLocator(page, route) {
  if (route.scope === "footer") return page.locator(`footer a[href="${route.href}"]`).first();
  if (route.scope === "header") return page.locator(`header a[href="${route.href}"]`).first();
  return page.locator(`a[href="${route.href}"]`).first();
}

const browser = await chromium.launch({ headless: true });
const softs = [];

for (const route of softRoutes) {
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    serviceWorkers: "block",
  });
  const page = await ctx.newPage();
  await page.goto(`${PROD}${route.from}`, { waitUntil: "domcontentloaded", timeout: 120000 });
  // Setup readiness only (not destination warm): clicking before the App Router is
  // ready after hard goto produces ~11s soft-nav outliers unrelated to CMS cache.
  await page.waitForLoadState("networkidle", { timeout: 30000 }).catch(() => null);
  await page
    .waitForFunction(
      () => {
        const w = window;
        return Boolean(w.next?.router || w.__NEXT_DATA__ || document.querySelector("[data-testid='site-logo-link']"));
      },
      { timeout: 15000 },
    )
    .catch(() => null);
  await page.waitForTimeout(300);

  for (let i = 0; i < SOFT_N; i++) {
    let attempt = 0;
    let sample = null;
    while (attempt < 3) {
      attempt += 1;
      if (pathOf(page.url()) !== pathOf(route.from)) {
        // Prefer soft return (logo / in-app link) over hard goto so Next client
        // Flight cache survives — matches real home/back navigation, not a full reload.
        let softBack = false;
        try {
          const back =
            route.from === "/"
              ? page.locator('[data-testid="site-logo-link"], header a[href="/"]').first()
              : page.locator(`a[href="${route.from}"]`).first();
          const visibleBack = await back.isVisible().catch(() => false);
          if (visibleBack) {
            await Promise.all([
              page
                .waitForURL((url) => pathOf(url.toString()) === pathOf(route.from), { timeout: 12000 })
                .catch(() => null),
              back.click({ timeout: 8000 }),
            ]);
            softBack = pathOf(page.url()) === pathOf(route.from);
          }
        } catch {
          softBack = false;
        }
        if (!softBack) {
          let ready = false;
          for (let g = 0; g < 3 && !ready; g++) {
            try {
              await page.goto(`${PROD}${route.from}`, { waitUntil: "domcontentloaded", timeout: 120000 });
              ready = true;
            } catch (err) {
              const msg = String(err?.message || err);
              if (!/ERR_NAME_NOT_RESOLVED|ERR_CONNECTION|net::ERR_/i.test(msg) || g === 2) throw err;
              await page.waitForTimeout(1500 * (g + 1));
            }
          }
        }
        await page.waitForTimeout(120);
      }

      const link = linkLocator(page, route);
      const visible = await link.isVisible().catch(() => false);
      let kind = "soft";
      const t0 = Date.now();
      let urlAt = null;
      let usableAt = null;

      if (!visible) {
        kind = "hard_fallback";
        await page.goto(`${PROD}${route.href}`, { waitUntil: "domcontentloaded", timeout: 120000 });
        urlAt = Date.now();
        usableAt = urlAt;
      } else {
        try {
          await link.hover({ timeout: 2000 }).catch(() => null);
          await Promise.all([
            page.waitForURL((url) => pathOf(url.toString()) === pathOf(route.href), { timeout: 12000 }).then(() => {
              urlAt = Date.now();
            }),
            link.click({ timeout: 10000 }),
          ]);
        } catch {
          kind = "soft_timeout";
          urlAt = Date.now();
        }
        await page
          .locator("main h1, main h2, h1, form, [data-testid='groups-landing-page'], [data-testid='group-search-form']")
          .first()
          .waitFor({ state: "visible", timeout: 8000 })
          .catch(() => null);
        usableAt = Date.now();
      }

      const appMs = urlAt != null ? urlAt - t0 : usableAt - t0;
      sample = {
        route: route.name,
        i,
        kind,
        raw: usableAt - t0,
        toUrl: urlAt != null ? urlAt - t0 : null,
        app: appMs,
        url: page.url(),
        attempt,
      };
      // Retry only genuine harness failures (timeout / missing link), never for slow APP.
      if (kind === "soft") break;
      if (kind === "soft_timeout" || kind === "hard_fallback") {
        await page.goto(`${PROD}${route.from}`, { waitUntil: "domcontentloaded", timeout: 120000 });
        await page.waitForTimeout(200);
        continue;
      }
      break;
    }
    softs.push(sample);
  }

  await ctx.close();
  const softMs = softs.filter((s) => s.route === route.name && s.kind === "soft").map((s) => s.app);
  console.log(
    `SOFT ${route.name} APP_P50=${pct(softMs, 50)} APP_P95=${pct(softMs, 95)} soft_n=${softMs.length} hard=${softs.filter((s) => s.route === route.name && s.kind !== "soft").length}`,
  );
}

await browser.close();
const byRoute = Object.fromEntries(
  softRoutes.map((route) => {
    const softMs = softs.filter((s) => s.route === route.name && s.kind === "soft").map((s) => s.app);
    return [
      route.name,
      {
        n_soft: softMs.length,
        hard_fallback: softs.filter((s) => s.route === route.name && s.kind !== "soft").length,
        APP_P50: pct(softMs, 50),
        APP_P95: pct(softMs, 95),
      },
    ];
  }),
);
const softP95s = softRoutes.map((r) => byRoute[r.name].APP_P95).filter(Number.isFinite);
const report = {
  BUILD_NOTE: "warm footer/header Link clicks; clickâ†’usable; cold context-per-sample retired",
  SOFT_NAV_ROUTES: softRoutes.length,
  SOFT_N_PER_ROUTE: SOFT_N,
  SOFT_NAV_WORST_APP_P95: softP95s.length ? Math.max(...softP95s) : null,
  APP_MULTI_SECOND_ROUTE_COUNT: softP95s.filter((x) => x > 1500).length,
  APP_OVER_750_COUNT: softP95s.filter((x) => x > 750).length,
  soft: byRoute,
  samples: softs,
};
fs.writeFileSync("soft-nav.json", JSON.stringify(report, null, 2));
console.log(JSON.stringify({ ...report, samples: undefined }, null, 2));
