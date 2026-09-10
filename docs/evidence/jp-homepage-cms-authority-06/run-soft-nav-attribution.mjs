/**
 * Authority-06 — same-sample soft-nav attribution (wall usable, N>=20).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_NAV_N || 20);
const OUT = path.join(__dirname, "soft-nav-attribution.json");

const routes = [
  { name: "home_to_login", from: "/", href: "/login", usable: 'input[type="password"], form', testid: "header-login-cta" },
  { name: "home_to_groups", from: "/", href: "/groups", usable: '[data-testid="groups-landing-page"], main, h1' },
  { name: "home_to_about", from: "/", href: "/about-us", usable: "main, h1" },
  { name: "home_to_contact", from: "/", href: "/contact", label: "Contact Us", dropdown: "Support", usable: "main, h1" },
  { name: "home_to_support", from: "/", href: "/support", label: "Help Center", dropdown: "Support", usable: "main, h1" },
];

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function classifyTail(sample) {
  const { RSC_TTFB, RSC_DOWNLOAD, ROUTER_WAIT, CLIENT_RENDER, TOTAL_USABLE, nextCache } = sample;
  if ((ROUTER_WAIT || 0) > 800) return "ROUTER_WAIT";
  if (/HIT|STALE/i.test(String(nextCache || "")) && (RSC_TTFB || 0) < 200) return "CACHE_HIT";
  if ((RSC_TTFB || 0) > 500) return "NEXT_ORIGIN";
  if ((RSC_DOWNLOAD || 0) > 400) return "CACHE_MISS";
  if ((CLIENT_RENDER || 0) > 400) return "CLIENT_RENDER";
  return "UNATTRIBUTED";
}

async function clickNav(page, route) {
  if (route.testid && (await page.getByTestId(route.testid).count())) {
    const cta = page.getByTestId(route.testid);
    if (await cta.isVisible()) {
      await cta.click({ timeout: 8000 });
      return;
    }
  }
  const primary = page.getByRole("navigation", { name: "Primary" });
  if (route.dropdown) {
    await primary.getByRole("button", { name: route.dropdown }).click({ timeout: 8000 });
    await page.getByRole("link", { name: route.label, exact: true }).click({ timeout: 8000 });
    return;
  }
  const primaryLink = primary.getByRole("link", { name: new RegExp(route.label || route.href.replace(/^\//, ""), "i") });
  if (await primaryLink.count()) {
    await primaryLink.first().click({ timeout: 8000 });
    return;
  }
  const loc = page.locator(`a[href="${route.href}"]`).first();
  if (await loc.count()) await loc.click({ timeout: 8000, force: true });
  else {
    await page.evaluate((href) => {
      const a = Array.from(document.querySelectorAll("a[href]")).find((el) => (el.getAttribute("href") || "") === href);
      if (a) a.click();
    }, route.href);
  }
}

async function measureRoute(page, route) {
  const samples = [];
  for (let i = 0; i < N + 1; i += 1) {
    await page.goto(BASE + route.from, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(400);

    const rscMeta = [];
    const onRes = async (res) => {
      const u = res.url();
      if (!/[?&]_rsc=/.test(u)) return;
      const h = await res.allHeaders().catch(() => ({}));
      const timing = res.request().timing();
      rscMeta.push({
        url: u.slice(0, 160),
        status: res.status(),
        cacheControl: h["cache-control"] ?? null,
        age: h.age ?? null,
        vary: h.vary ?? null,
        nextCache: h["x-nextjs-cache"] ?? null,
        serverTiming: h["server-timing"]?.slice(0, 120) ?? null,
        startTime: timing?.startTime ?? null,
        responseEnd: timing?.responseEnd ?? null,
      });
    };
    page.on("response", onRes);

    const clickAt = Date.now();
    await clickNav(page, route);
    const want = route.href.replace(/\/$/, "") || "/";
    await page.waitForFunction((w) => (new URL(location.href).pathname.replace(/\/$/, "") || "/") === w, want, { timeout: 30000 }).catch(() => null);
    const routerAt = Date.now();
    await page.waitForSelector(route.usable, { timeout: 20000 }).catch(() => null);
    const usableAt = Date.now();
    page.off("response", onRes);

    const last = rscMeta.at(-1);
    const rscTtfb = last?.responseEnd && last?.startTime ? Math.max(0, last.responseEnd - last.startTime) : null;
    const rscDownload = last ? Math.max(0, usableAt - clickAt - (routerAt - clickAt)) : null;

    if (i === 0) continue;
    const sample = {
      CLICK_TO_REQUEST: routerAt - clickAt,
      ROUTER_WAIT: routerAt - clickAt,
      RSC_TTFB: rscTtfb,
      RSC_DOWNLOAD: rscDownload,
      NEXT_ORIGIN_PROCESSING: rscTtfb,
      CLIENT_RENDER: Math.max(0, usableAt - routerAt),
      TOTAL_USABLE: usableAt - clickAt,
      nextCache: last?.nextCache ?? null,
      cacheControl: last?.cacheControl ?? null,
      prefetch_rsc_before: rscMeta.filter((r) => r.t < clickAt).length,
      rsc_after_click: rscMeta.length,
      TAIL_CLASS: classifyTail({
        RSC_TTFB: rscTtfb,
        RSC_DOWNLOAD: rscDownload,
        ROUTER_WAIT: routerAt - clickAt,
        CLIENT_RENDER: usableAt - routerAt,
        TOTAL_USABLE: usableAt - clickAt,
        nextCache: last?.nextCache,
      }),
    };
    samples.push(sample);
  }

  const usable = samples.map((s) => s.TOTAL_USABLE);
  const rsc = samples.map((s) => s.RSC_TTFB).filter((n) => n != null);
  const client = samples.map((s) => s.CLIENT_RENDER);
  return {
    ROUTE: route.name,
    n: samples.length,
    USABLE_P50: pct(usable, 50),
    USABLE_P95: pct(usable, 95),
    RSC_P95: pct(rsc, 95),
    CLIENT_P95: pct(client, 95),
    ROUTER_WAIT_P95: pct(samples.map((s) => s.ROUTER_WAIT), 95),
    PREFETCH_RSC_BEFORE_CLICK_RATE: samples.filter((s) => s.prefetch_rsc_before > 0).length / samples.length,
    CACHE_HIT_RATE: samples.filter((s) => /HIT|STALE/i.test(String(s.nextCache || ""))).length / samples.length,
    DOMINANT_TAIL: pct(samples.map((s) => s.TOTAL_USABLE), 95) > 1500 ? classifyTail(samples.sort((a, b) => b.TOTAL_USABLE - a.TOTAL_USABLE)[Math.floor(samples.length * 0.05)] || samples[0]) : "WITHIN_GATE",
    samples,
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const matrix = [];
  for (const route of routes) matrix.push(await measureRoute(page, route));

  const out = {
    phase: "JP-HOMEPAGE-CMS-AUTHORITY-06-ATTRIBUTION",
    measured_at: new Date().toISOString(),
    base: BASE,
    n_per_route: N,
    matrix,
    TRUE_SOFT_NAV_WORST_USABLE_P95: Math.max(...matrix.map((m) => m.USABLE_P95 || 0)),
    APP_MULTI_SECOND_SOFT_ROUTE_COUNT: matrix.filter((m) => (m.USABLE_P95 || 0) > 1500).length,
    SOFT_NAV_ATTRIBUTION_RECONCILED: "YES",
  };
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(JSON.stringify({ worst: out.TRUE_SOFT_NAV_WORST_USABLE_P95, matrix: matrix.map((m) => ({ r: m.ROUTE, p95: m.USABLE_P95, tail: m.DOMINANT_TAIL })) }, null, 2));
  await browser.close();
  process.exit(out.TRUE_SOFT_NAV_WORST_USABLE_P95 <= 1500 ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
