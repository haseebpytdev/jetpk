/**
 * Authority-06 R3 — validate ROUTER_WAIT / RSC_END_TO_ROUTE_COMMIT measurement (read-only).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_NAV_N || 20);
const OUT = path.join(__dirname, "router-wait-harness-validation.json");

const ROUTES = [
  {
    name: "home_to_login",
    href: "/login",
    testid: "header-login-cta",
    rootSelector: '[data-testid="login-form"]',
    interactiveSelector: '[data-testid="login-form"] input[type="password"]:not([disabled])',
  },
  {
    name: "home_to_support",
    href: "/support",
    dropdown: { menu: "Support", label: "Help Center" },
    rootSelector: "#support-page-heading",
    interactiveSelector: "#support-form-heading",
  },
];

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
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
    const primary = page.getByRole("navigation", { name: "Primary" });
    const direct = page.locator(`a[href="${route.href}"]`).first();
    if (await direct.count()) {
      try {
        await direct.click({ timeout: 5000, force: true });
        return;
      } catch {
        /* menu */
      }
    }
    await primary.getByRole("button", { name: route.dropdown.menu }).click({ timeout: 8000, force: true });
    await page.getByRole("link", { name: route.dropdown.label, exact: true }).click({ timeout: 8000, force: true });
    return;
  }
  await page.locator(`a[href="${route.href}"]`).first().click({ timeout: 8000, force: true });
}

async function installProbe(page, route) {
  await page.evaluate((cfg) => {
    if (window.__jpNavProbe?.observer) window.__jpNavProbe.observer.disconnect();
    if (window.__jpNavProbe?.pathPoll) clearInterval(window.__jpNavProbe.pathPoll);
    const probe = {
      clickPerfTs: null,
      historyUrlChangePerfTs: null,
      routeTreeChangePerfTs: null,
      firstDomMutationPerfTs: null,
      destinationRootVisiblePerfTs: null,
      destinationInteractivePerfTs: null,
      wantPath: cfg.href.replace(/\/$/, "") || "/",
    };
    const main = document.getElementById("main-content");
    const visible = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return false;
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0;
    };
    const interactive = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return false;
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && !el.disabled && !el.closest("[aria-hidden='true']");
    };
    const tick = () => {
      const path = (location.pathname.replace(/\/$/, "") || "/");
      if (path === probe.wantPath) {
        if (!probe.historyUrlChangePerfTs) probe.historyUrlChangePerfTs = performance.now();
        if (!probe.destinationRootVisiblePerfTs && visible(cfg.rootSelector)) {
          probe.destinationRootVisiblePerfTs = performance.now();
        }
        if (!probe.destinationInteractivePerfTs && interactive(cfg.interactiveSelector)) {
          probe.destinationInteractivePerfTs = performance.now();
        }
      }
    };
    const observer = new MutationObserver(() => {
      if (!probe.firstDomMutationPerfTs) probe.firstDomMutationPerfTs = performance.now();
      if (!probe.routeTreeChangePerfTs && probe.firstDomMutationPerfTs) {
        probe.routeTreeChangePerfTs = probe.firstDomMutationPerfTs;
      }
      tick();
    });
    if (main) observer.observe(main, { childList: true, subtree: true, attributes: true, characterData: true });
    probe.pathPoll = setInterval(tick, 8);
    probe.observer = observer;
    window.__jpNavProbe = probe;
  }, route);
}

function classifyUrl(url) {
  if (/[?&]_rsc=/.test(url)) return "rsc";
  if (/\/laravel\/api\/public\/auth\/session/.test(url)) return "session";
  if (/\/laravel\/api\/public\/content\/config/.test(url)) return "public_config";
  if (/\/_next\/static\/chunks\//.test(url)) return "chunk";
  if (/\.(woff2?|ttf|otf)(\?|$)/i.test(url)) return "font";
  if (/\.(png|jpe?g|webp|svg|gif)(\?|$)/i.test(url)) return "image";
  if (url.includes("_rsc=")) return "rsc";
  return "other";
}

async function measureSample(page, route) {
  const rscEvents = [];
  const allRequests = [];
  const onReq = (req) => {
    const url = req.url();
    const cls = classifyUrl(url);
    const ts = Date.now();
    const entry = { url: url.slice(0, 180), cls, startTs: ts, endTs: null, transferSize: null };
    allRequests.push(entry);
    req.__jpEntry = entry;
    if (cls === "rsc") rscEvents.push({ phase: "start", ts, url: url.slice(0, 160) });
  };
  const onRes = async (res) => {
    const req = res.request();
    const entry = req.__jpEntry;
    const ts = Date.now();
    if (entry) {
      entry.endTs = ts;
      try {
        const sizes = await res.body().then((b) => b.length).catch(() => null);
        entry.transferSize = sizes;
      } catch {
        /* ignore */
      }
    }
    if (classifyUrl(res.url()) === "rsc") {
      const h = await res.allHeaders().catch(() => ({}));
      rscEvents.push({
        phase: "headers",
        ts,
        url: res.url().slice(0, 160),
        nextCache: h["x-nextjs-cache"] ?? null,
      });
      rscEvents.push({ phase: "end", ts, url: res.url().slice(0, 160) });
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

  await page
    .waitForSelector(route.interactiveSelector, { timeout: 30000, state: "visible" })
    .catch(() => null);

  const probe = await page.evaluate(() => {
    if (window.__jpNavProbe?.pathPoll) clearInterval(window.__jpNavProbe.pathPoll);
    if (window.__jpNavProbe?.observer) window.__jpNavProbe.observer.disconnect();
    return window.__jpNavProbe;
  });

  page.off("request", onReq);
  page.off("response", onRes);

  const clickPerfTs = probe.clickPerfTs ?? 0;
  const toMs = (perfTs) => (perfTs == null ? null : Math.round(perfTs - clickPerfTs));

  const navRsc = rscEvents.filter((e) => e.phase === "start");
  const rscStartTs = navRsc.length ? navRsc[0].ts : null;
  const rscHeadersTs = rscEvents.find((e) => e.phase === "headers")?.ts ?? null;
  const rscEndTs = rscEvents.filter((e) => e.phase === "end").at(-1)?.ts ?? null;

  const historyUrlChangeTs = probe.historyUrlChangePerfTs != null ? clickTs + toMs(probe.historyUrlChangePerfTs) : null;
  const routeTreeChangeTs = probe.routeTreeChangePerfTs != null ? clickTs + toMs(probe.routeTreeChangePerfTs) : null;
  const firstDomMutationTs = probe.firstDomMutationPerfTs != null ? clickTs + toMs(probe.firstDomMutationPerfTs) : null;
  const destinationRootVisibleTs =
    probe.destinationRootVisiblePerfTs != null ? clickTs + toMs(probe.destinationRootVisiblePerfTs) : null;
  const destinationInteractiveTs =
    probe.destinationInteractivePerfTs != null ? clickTs + toMs(probe.destinationInteractivePerfTs) : null;

  const routeCommitTs = historyUrlChangeTs ?? routeTreeChangeTs ?? firstDomMutationTs;
  const trueUsableTs = destinationInteractiveTs ?? destinationRootVisibleTs;

  const oldHarnessRouterWait = routeCommitTs != null ? routeCommitTs - clickTs : null;
  const trueRscEndToRouteCommit =
    rscEndTs != null && routeCommitTs != null ? Math.max(0, routeCommitTs - rscEndTs) : null;
  const trueRouteCommitToUsable =
    routeCommitTs != null && trueUsableTs != null ? Math.max(0, trueUsableTs - routeCommitTs) : null;
  const totalUsable = trueUsableTs != null ? trueUsableTs - clickTs : null;

  const navWindow = { start: clickTs, end: trueUsableTs ?? clickTs + 30000 };
  const overlapping = allRequests.filter((r) => r.startTs <= navWindow.end && (r.endTs ?? navWindow.end) >= navWindow.start);

  const deferredPrefetchOverlap = overlapping.some(
    (r) => r.cls === "rsc" && r.startTs > clickTs && r.url.includes(route.href) === false,
  );

  return {
    CLICK_TS: clickTs,
    RSC_REQUEST_START_TS: rscStartTs,
    RSC_HEADERS_TS: rscHeadersTs,
    RSC_END_TS: rscEndTs,
    HISTORY_URL_CHANGE_TS: historyUrlChangeTs,
    NEXT_ROUTE_TREE_CHANGE_TS: routeTreeChangeTs,
    FIRST_DESTINATION_DOM_MUTATION_TS: firstDomMutationTs,
    DESTINATION_ROOT_VISIBLE_TS: destinationRootVisibleTs,
    DESTINATION_INTERACTIVE_TS: destinationInteractiveTs,
    OLD_HARNESS_ROUTER_WAIT_MS: oldHarnessRouterWait,
    TRUE_RSC_END_TO_ROUTE_COMMIT_MS: trueRscEndToRouteCommit,
    TRUE_ROUTE_COMMIT_TO_USABLE_MS: trueRouteCommitToUsable,
    TOTAL_USABLE_MS: totalUsable,
    CLICK_TO_RSC_START_MS: rscStartTs != null ? rscStartTs - clickTs : null,
    RSC_COMPETES_FOR_CONNECTION: overlapping.some(
      (a) => a.cls === "rsc" && overlapping.some((b) => b !== a && b.cls !== "rsc" && b.startTs < (a.endTs ?? navWindow.end) && (b.endTs ?? navWindow.end) > a.startTs),
    )
      ? "YES"
      : "NO",
    overlapping_requests: overlapping.map((r) => ({
      cls: r.cls,
      start: r.startTs - clickTs,
      end: (r.endTs ?? navWindow.end) - clickTs,
      url: r.url.slice(0, 100),
    })),
    DEFERRED_PREFETCH_QUEUE_OVERLAPS_NAV: deferredPrefetchOverlap ? "YES" : "NO",
  };
}

async function measureRoute(browser, route) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const samples = [];
  for (let i = 0; i < N + 1; i += 1) {
    await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
    try {
      samples.push(await measureSample(page, route));
    } catch (err) {
      samples.push({ error: String(err?.message || err) });
    }
  }
  await context.close();
  const valid = samples.slice(1).filter((s) => !s.error && s.TOTAL_USABLE_MS != null);
  const oldRouter = valid.map((s) => s.OLD_HARNESS_ROUTER_WAIT_MS).filter((n) => n != null);
  const rscToCommit = valid.map((s) => s.TRUE_RSC_END_TO_ROUTE_COMMIT_MS).filter((n) => n != null);
  const commitToUsable = valid.map((s) => s.TRUE_ROUTE_COMMIT_TO_USABLE_MS).filter((n) => n != null);
  const usable = valid.map((s) => s.TOTAL_USABLE_MS);
  const inflated =
    valid.filter(
      (s) =>
        s.OLD_HARNESS_ROUTER_WAIT_MS != null &&
        s.TRUE_RSC_END_TO_ROUTE_COMMIT_MS != null &&
        s.RSC_END_TS != null &&
        s.OLD_HARNESS_ROUTER_WAIT_MS - (s.RSC_END_TS - s.CLICK_TS) > 400,
    ).length / Math.max(1, valid.length);
  return {
    route: route.name,
    n: valid.length,
    USABLE_P95: pct(usable, 95),
    OLD_HARNESS_ROUTER_WAIT_P95: pct(oldRouter, 95),
    TRUE_RSC_END_TO_ROUTE_COMMIT_P95: pct(rscToCommit, 95),
    TRUE_ROUTE_COMMIT_TO_USABLE_P95: pct(commitToUsable, 95),
    HARNESS_INFLATION_RATE: Math.round(inflated * 100) / 100,
    samples: valid,
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const routes = [];
  for (const route of ROUTES) routes.push(await measureRoute(browser, route));
  await browser.close();

  const login = routes.find((r) => r.route === "home_to_login");
  const support = routes.find((r) => r.route === "home_to_support");
  const harnessInflation =
    ((login?.HARNESS_INFLATION_RATE ?? 0) + (support?.HARNESS_INFLATION_RATE ?? 0)) / 2;
  const out = {
    phase: "AUTHORITY-06-R3-HARNESS-VALIDATION",
    captured_at: new Date().toISOString(),
    production_sha: "8c50fc61967e51c5b576375b55ae7701d122c121",
    base: BASE,
    n_per_route: N,
    HARNESS_ROUTER_WAIT_VALID: harnessInflation > 0.5 ? "NO" : "YES",
    HARNESS_ARTIFICIAL_WAIT_MS: 0,
    TRUE_RSC_END_TO_ROUTE_COMMIT_P95: Math.max(
      login?.TRUE_RSC_END_TO_ROUTE_COMMIT_P95 ?? 0,
      support?.TRUE_RSC_END_TO_ROUTE_COMMIT_P95 ?? 0,
    ),
    TRUE_ROUTE_COMMIT_TO_USABLE_P95: Math.max(
      login?.TRUE_ROUTE_COMMIT_TO_USABLE_P95 ?? 0,
      support?.TRUE_ROUTE_COMMIT_TO_USABLE_P95 ?? 0,
    ),
    routes,
  };
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(
    JSON.stringify(
      {
        HARNESS_ROUTER_WAIT_VALID: out.HARNESS_ROUTER_WAIT_VALID,
        TRUE_RSC_END_TO_ROUTE_COMMIT_P95: out.TRUE_RSC_END_TO_ROUTE_COMMIT_P95,
        TRUE_ROUTE_COMMIT_TO_USABLE_P95: out.TRUE_ROUTE_COMMIT_TO_USABLE_P95,
        login_p95: login?.USABLE_P95,
        support_p95: support?.USABLE_P95,
      },
      null,
      2,
    ),
  );
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
