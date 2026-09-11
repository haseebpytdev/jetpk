/**
 * Authority-06 R4 — local prefetch A/B benchmark (0ms early-click).
 * Usage: JP_COHORT=A|B JP_BASE_URL=http://127.0.0.1:3002 node run-r4-prefetch-ab-local.mjs
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "http://127.0.0.1:3002";
const COHORT = process.env.JP_COHORT || "A";
const OUT = path.join(__dirname, `r4-prefetch-ab-local-${COHORT.toLowerCase()}.json`);

const ROUTES = [
  {
    name: "home_to_login",
    href: "/login",
    n: Number(process.env.JP_LOGIN_N || 30),
    testid: "header-login-cta",
    rootSelector: '[data-testid="login-form"]',
    interactiveSelector: '[data-testid="login-form"] input[type="password"]:not([disabled])',
  },
  {
    name: "home_to_groups",
    href: "/groups",
    n: Number(process.env.JP_NAV_N || 20),
    click: "primary",
    rootSelector: '[data-testid="groups-landing-page"], main h1',
    interactiveSelector: '[data-testid="groups-landing-page"], main h1',
  },
  {
    name: "home_to_about",
    href: "/about-us",
    n: Number(process.env.JP_NAV_N || 20),
    rootSelector: "main h1",
    interactiveSelector: "main h1",
  },
  {
    name: "home_to_contact",
    href: "/contact",
    n: Number(process.env.JP_NAV_N || 20),
    dropdown: { menu: "Support", label: "Contact Us" },
    rootSelector: "main h1",
    interactiveSelector: "main h1",
  },
  {
    name: "home_to_support",
    href: "/support",
    n: Number(process.env.JP_NAV_N || 20),
    dropdown: { menu: "Support", label: "Help Center" },
    rootSelector: "#support-page-heading",
    interactiveSelector: "#support-form-heading",
  },
];

const AUTH_PATHS = ["/login", "/register"];

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function summarize(samples) {
  const pick = (k) => samples.map((s) => s[k]).filter((n) => typeof n === "number" && Number.isFinite(n));
  return {
    n: samples.length,
    TOTAL_USABLE_P50: pct(pick("TOTAL_USABLE_MS"), 50),
    TOTAL_USABLE_P95: pct(pick("TOTAL_USABLE_MS"), 95),
    CLICK_TO_RSC_START_P95: pct(pick("CLICK_TO_RSC_START_MS"), 95),
    RSC_TTFB_P95: pct(pick("RSC_TTFB_MS"), 95),
    RSC_END_TO_ROUTE_COMMIT_P95: pct(pick("RSC_END_TO_ROUTE_COMMIT_MS"), 95),
    ROUTE_COMMIT_TO_USABLE_P95: pct(pick("ROUTE_COMMIT_TO_USABLE_MS"), 95),
    RSC_REQUEST_COUNT_P95: pct(pick("RSC_REQUEST_COUNT"), 95),
    PROGRAMMATIC_PREFETCH_RSC_COUNT_P95: pct(pick("PROGRAMMATIC_PREFETCH_RSC_COUNT"), 95),
    DUPLICATE_RSC_COUNT_P95: pct(pick("DUPLICATE_RSC_COUNT"), 95),
    CHUNK_REQUEST_COUNT_P95: pct(pick("CHUNK_REQUEST_COUNT"), 95),
    OVERLAPPING_CHUNK_COUNT_P95: pct(pick("OVERLAPPING_CHUNK_COUNT"), 95),
    LONG_TASK_P95: pct(pick("LONG_TASK_MS"), 95),
    UNRELATED_AUTH_PREFETCH_ACTIVE_RATE:
      samples.filter((s) => s.UNRELATED_AUTH_PREFETCH_ACTIVE_DURING_NAV === "YES").length / Math.max(1, samples.length),
    UNRELATED_AUTH_CHUNK_LOAD_RATE:
      samples.filter((s) => s.UNRELATED_AUTH_CHUNK_LOAD_DURING_NAV === "YES").length / Math.max(1, samples.length),
  };
}

async function clickNav(page, route) {
  if (route.testid) {
    const cta = page.getByTestId(route.testid);
    if (await cta.isVisible()) {
      await cta.click({ timeout: 8000 });
      return;
    }
  }
  if (route.dropdown) {
    const direct = page.locator(`a[href="${route.href}"]`).first();
    if (await direct.count()) {
      try {
        await direct.click({ timeout: 5000, force: true });
        return;
      } catch {
        /* menu */
      }
    }
    const primary = page.getByRole("navigation", { name: "Primary" });
    await primary.getByRole("button", { name: route.dropdown.menu }).click({ timeout: 8000, force: true });
    await page.getByRole("link", { name: route.dropdown.label, exact: true }).click({ timeout: 8000, force: true });
    return;
  }
  if (route.click === "primary") {
    const link = page.locator('nav[aria-label="Primary"] a[href="/groups"]').first();
    await link.click({ timeout: 8000 });
    return;
  }
  await page.locator(`a[href="${route.href}"]`).first().click({ timeout: 8000, force: true });
}

function rscPath(url) {
  try {
    const u = new URL(url);
    return u.pathname.replace(/\/$/, "") || "/";
  } catch {
    return null;
  }
}

async function installProbe(page, route) {
  await page.evaluate((cfg) => {
    if (window.__jpNavProbe?.observer) window.__jpNavProbe.observer.disconnect();
    if (window.__jpNavProbe?.pathPoll) clearInterval(window.__jpNavProbe.pathPoll);
    window.__jpLongTasks = [];
    try {
      const obs = new PerformanceObserver((list) => {
        for (const e of list.getEntries()) window.__jpLongTasks.push({ start: e.startTime, duration: e.duration });
      });
      obs.observe({ type: "longtask", buffered: true });
    } catch {
      /* noop */
    }
    const probe = {
      clickPerfTs: null,
      historyUrlChangePerfTs: null,
      firstDomMutationPerfTs: null,
      destinationInteractivePerfTs: null,
      wantPath: cfg.href.replace(/\/$/, "") || "/",
    };
    const main = document.getElementById("main-content");
    const interactive = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return false;
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && !el.disabled;
    };
    const tick = () => {
      const path = location.pathname.replace(/\/$/, "") || "/";
      if (path === probe.wantPath) {
        if (!probe.historyUrlChangePerfTs) probe.historyUrlChangePerfTs = performance.now();
        if (!probe.destinationInteractivePerfTs && interactive(cfg.interactiveSelector)) {
          probe.destinationInteractivePerfTs = performance.now();
        }
      }
    };
    const observer = new MutationObserver(() => {
      if (!probe.firstDomMutationPerfTs) probe.firstDomMutationPerfTs = performance.now();
      tick();
    });
    if (main) observer.observe(main, { childList: true, subtree: true, attributes: true });
    probe.pathPoll = setInterval(tick, 8);
    probe.observer = observer;
    window.__jpNavProbe = probe;
  }, route);
}

async function measureSample(page, route) {
  const rscLog = [];
  const chunks = [];
  const onReq = (req) => {
    const url = req.url();
    const ts = Date.now();
    if (/[?&]_rsc=/.test(url)) rscLog.push({ url, path: rscPath(url), phase: "start", ts });
    if (/\/_next\/static\/chunks\//.test(url)) chunks.push({ url, start: ts, end: null });
  };
  const onRes = async (res) => {
    const url = res.url();
    const ts = Date.now();
    if (/[?&]_rsc=/.test(url)) {
      const h = await res.allHeaders().catch(() => ({}));
      const timing = res.request().timing();
      rscLog.push({
        url,
        path: rscPath(url),
        phase: "end",
        ts,
        ttfb: timing?.responseStart != null && timing?.requestStart != null
          ? Math.max(0, timing.responseStart - timing.requestStart)
          : null,
        nextCache: h["x-nextjs-cache"] ?? null,
      });
    }
    if (/\/_next\/static\/chunks\//.test(url)) {
      const c = chunks.find((x) => x.url === url && x.end == null);
      if (c) c.end = ts;
    }
  };
  page.on("request", onReq);
  page.on("response", onRes);

  await installProbe(page, route);
  const clickTs = Date.now();
  await page.evaluate(() => {
    window.__jpNavProbe.clickPerfTs = performance.now();
  });
  await clickNav(page, route);
  await page.waitForSelector(route.interactiveSelector, { timeout: 30000, state: "visible" }).catch(() => null);
  const endTs = Date.now();

  const probe = await page.evaluate(() => {
    if (window.__jpNavProbe?.pathPoll) clearInterval(window.__jpNavProbe.pathPoll);
    if (window.__jpNavProbe?.observer) window.__jpNavProbe.observer.disconnect();
    return { probe: window.__jpNavProbe, longTasks: window.__jpLongTasks || [] };
  });
  page.off("request", onReq);
  page.off("response", onRes);

  const clickPerf = probe.probe.clickPerfTs ?? 0;
  const toMs = (perfTs) => (perfTs == null ? null : Math.round(perfTs - clickPerf));
  const routeCommitMs = toMs(probe.probe.historyUrlChangePerfTs);
  const usableMs = toMs(probe.probe.destinationInteractivePerfTs) ?? endTs - clickTs;

  const navRscStarts = rscLog.filter((e) => e.phase === "start");
  const navRscEnds = rscLog.filter((e) => e.phase === "end");
  const destPath = route.href.replace(/\/$/, "") || "/";
  const destRscEnds = navRscEnds.filter((e) => e.path === destPath);
  const firstRscStart = navRscStarts[0]?.ts ?? null;
  const lastDestRscEnd = destRscEnds.at(-1)?.ts ?? navRscEnds.at(-1)?.ts ?? null;
  const rscTtfb = destRscEnds.find((e) => e.ttfb != null)?.ttfb ?? navRscEnds.find((e) => e.ttfb != null)?.ttfb ?? null;

  const rscEndToCommit =
    lastDestRscEnd != null && routeCommitMs != null && probe.probe.historyUrlChangePerfTs != null
      ? Math.max(0, clickTs + routeCommitMs - lastDestRscEnd)
      : null;
  const commitToUsable =
    routeCommitMs != null && usableMs != null ? Math.max(0, usableMs - routeCommitMs) : null;

  const rscPaths = navRscStarts.map((e) => e.path).filter(Boolean);
  const duplicateRsc = rscPaths.length - new Set(rscPaths).size;
  const prefetchRsc = navRscStarts.filter((e) => e.path && e.path !== destPath && e.ts <= (lastDestRscEnd ?? endTs));
  const unrelatedAuth = navRscStarts.filter(
    (e) => e.path && AUTH_PATHS.includes(e.path) && e.path !== destPath && e.ts < clickTs + (usableMs ?? 0),
  );
  const authChunks = chunks.filter(
    (c) =>
      /\(auth\)|login|register/.test(c.url) &&
      c.start >= clickTs &&
      c.start <= endTs &&
      !destPath.includes("login") &&
      !destPath.includes("register"),
  );

  const winStart = clickPerf;
  const winEnd = clickPerf + usableMs;
  const longInWin = probe.longTasks.filter((t) => t.start >= winStart && t.start <= winEnd);
  const longTaskMs = longInWin.reduce((s, t) => s + t.duration, 0);
  const overlappingChunks = chunks.filter((c) => c.start <= endTs && (c.end ?? endTs) >= clickTs);

  return {
    TOTAL_USABLE_MS: usableMs,
    CLICK_TO_RSC_START_MS: firstRscStart != null ? firstRscStart - clickTs : null,
    RSC_TTFB_MS: rscTtfb != null ? Math.round(rscTtfb) : null,
    RSC_END_TO_ROUTE_COMMIT_MS: rscEndToCommit,
    ROUTE_COMMIT_TO_USABLE_MS: commitToUsable,
    RSC_REQUEST_COUNT: navRscStarts.length,
    PROGRAMMATIC_PREFETCH_RSC_COUNT: prefetchRsc.length,
    DUPLICATE_RSC_COUNT: duplicateRsc,
    CHUNK_REQUEST_COUNT: chunks.length,
    OVERLAPPING_CHUNK_COUNT: overlappingChunks.length,
    LONG_TASK_MS: Math.round(longTaskMs),
    UNRELATED_AUTH_PREFETCH_ACTIVE_DURING_NAV: unrelatedAuth.length > 0 ? "YES" : "NO",
    UNRELATED_AUTH_CHUNK_LOAD_DURING_NAV: authChunks.length > 0 ? "YES" : "NO",
  };
}

async function measureRoute(browser, route) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const samples = [];
  for (let i = 0; i < route.n + 1; i += 1) {
    await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
    try {
      samples.push(await measureSample(page, route));
    } catch (e) {
      samples.push({ error: String(e?.message || e) });
    }
  }
  await context.close();
  const valid = samples.slice(1).filter((s) => !s.error);
  return { route: route.name, ...summarize(valid), samples: valid };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const matrix = [];
  for (const route of ROUTES) matrix.push(await measureRoute(browser, route));
  await browser.close();

  const worst = Math.max(...matrix.map((m) => m.TOTAL_USABLE_P95 || 0));
  const out = {
    phase: "AUTHORITY-06-R4-LOCAL-AB",
    cohort: COHORT,
    captured_at: new Date().toISOString(),
    base: BASE,
    matrix,
    LOCAL_TRUE_SOFT_NAV_WORST_P95: worst,
  };
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(JSON.stringify({ cohort: COHORT, worst, matrix: matrix.map((m) => ({ r: m.route, p95: m.TOTAL_USABLE_P95 })) }, null, 2));
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
