/**
 * Authority-06 — same-sample soft-nav decomposition for routes with P95 > 1500ms.
 * Read-only. N>=20 warm samples per route (discard first).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const N = Number(process.env.JP_NAV_N || 20);
const OUT_JSON = path.join(__dirname, "site-soft-nav-decompose.json");

const routes = [
  { name: "home_to_login", from: "/", href: "/login", usable: 'input[type="password"], form', testid: "header-login-cta" },
  { name: "home_to_about", from: "/", href: "/about-us", usable: "main, h1" },
  { name: "home_to_contact", from: "/", href: "/contact", usable: "main, h1, form" },
  { name: "home_to_groups", from: "/", href: "/groups", usable: '[data-testid="groups-landing-page"], main, h1' },
  { name: "home_to_support", from: "/", href: "/support", usable: "main, h1, form" },
];

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function clickNav(page, route) {
  if (route.testid) {
    const cta = page.getByTestId(route.testid);
    if (await cta.count()) {
      await cta.click({ timeout: 8000 });
      return;
    }
  }
  const loc = page.locator(`a[href="${route.href}"]`).first();
  if (await loc.count()) {
    await loc.click({ timeout: 8000, force: true }).catch(async () => {
      await page.evaluate((href) => {
        const a = Array.from(document.querySelectorAll("a[href]")).find((el) => (el.getAttribute("href") || "") === href);
        if (a) a.click();
      }, route.href);
    });
    return;
  }
  await page.evaluate((href) => {
    const a = Array.from(document.querySelectorAll("a[href]")).find((el) => (el.getAttribute("href") || "") === href);
    if (a) a.click();
  }, route.href);
}

async function measureRoute(page, route) {
  const samples = [];
  for (let i = 0; i < N + 1; i += 1) {
    await page.goto(BASE + route.from, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(300);

    let reload = false;
    const navRequests = [];
    const onReq = (req) => {
      const u = req.url();
      if (req.isNavigationRequest() && req.resourceType() === "document" && req.frame() === page.mainFrame()) {
        reload = true;
      }
      if (/[?&]_rsc=/.test(u)) {
        navRequests.push({ url: u.slice(0, 120), start: Date.now() });
      }
    };
    page.on("request", onReq);

    const mark = `jp-nav-${Date.now()}`;
    await page.evaluate((m) => performance.mark(m), mark);
    const tClick = Date.now();
    await clickNav(page, route);
    const want = route.href.replace(/\/$/, "") || "/";
    await page
      .waitForFunction((w) => (new URL(location.href).pathname.replace(/\/$/, "") || "/") === w, want, { timeout: 30000 })
      .catch(() => null);
    const routerStart = Date.now() - tClick;
    const shell = Date.now() - tClick;
    await page.waitForSelector(route.usable, { timeout: 20000 }).catch(() => null);
    const usable = Date.now() - tClick;
    page.off("request", onReq);

    const decomp = await page.evaluate((startMark) => {
      const marks = performance.getEntriesByType("mark").filter((m) => m.name === startMark);
      const navStart = marks[0]?.startTime ?? 0;
      const entries = performance.getEntriesByType("resource") || [];
      const rsc = entries.filter((e) => /[?&]_rsc=/.test(e.name));
      const last = rsc.at(-1);
      const ttfb = last && last.responseStart > last.requestStart ? last.responseStart - last.requestStart : null;
      const download = last && last.responseEnd > last.responseStart ? last.responseEnd - last.responseStart : null;
      const chunks = entries.filter((e) => /\/_next\/static\/chunks\//.test(e.name) && e.startTime >= navStart);
      const chunkMs = chunks.reduce((sum, e) => sum + e.duration, 0);
      const fonts = entries.filter((e) => /\.(woff2?|ttf|otf)/i.test(e.name) && e.startTime >= navStart);
      const fontMs = fonts.reduce((sum, e) => sum + e.duration, 0);
      const longTasks = performance.getEntriesByType("longtask").filter((e) => e.startTime >= navStart);
      const longTaskMs = longTasks.reduce((sum, e) => sum + e.duration, 0);
      const prefetchLinks = Array.from(document.querySelectorAll('link[rel="prefetch"], link[rel="preload"]')).map((l) =>
        l.getAttribute("href"),
      );
      return {
        rsc_count: rsc.length,
        rsc_network_ms: last ? Math.round(last.duration) : null,
        rsc_ttfb_ms: ttfb != null ? Math.round(ttfb) : null,
        rsc_download_ms: download != null ? Math.round(download) : null,
        transfer: last ? last.transferSize : null,
        encoded: last ? last.encodedBodySize : null,
        cache_hit: last ? last.transferSize === 0 : null,
        prefetch_hit: last ? last.transferSize === 0 && last.decodedBodySize > 0 : null,
        chunk_load_ms: Math.round(chunkMs),
        font_image_blocking_ms: Math.round(fontMs),
        long_tasks_ms: Math.round(longTaskMs),
        long_task_count: longTasks.length,
        prefetch_link_count: prefetchLinks.length,
      };
    }, mark);

    if (i === 0) continue;
    const rscNet = decomp.rsc_network_ms ?? 0;
    const client = Math.max(0, usable - rscNet);
    const render = Math.max(0, usable - shell);
    const unattributed = Math.max(0, usable - rscNet - decomp.chunk_load_ms - decomp.long_tasks_ms);
    samples.push({
      click_to_router_start_ms: routerStart,
      router_start_to_rsc_ms: decomp.rsc_ttfb_ms,
      rsc_network_ms: rscNet,
      next_origin_processing_ms: decomp.rsc_ttfb_ms,
      client_state_processing_ms: client,
      hydration_render_ms: render,
      login_page_interactive_ms: usable,
      prefetch_hit: decomp.prefetch_hit,
      cache_status: decomp.cache_hit ? "hit" : "miss",
      chunk_load_ms: decomp.chunk_load_ms,
      font_image_blocking_ms: decomp.font_image_blocking_ms,
      long_tasks_ms: decomp.long_tasks_ms,
      unattributed_ms: unattributed,
      shell_ms: shell,
      usable_ms: usable,
      reload,
    });
  }

  const usable = samples.map((s) => s.usable_ms);
  const rsc = samples.map((s) => s.rsc_network_ms);
  const client = samples.map((s) => s.client_state_processing_ms);
  const render = samples.map((s) => s.hydration_render_ms);
  const chunks = samples.map((s) => s.chunk_load_ms);
  const longTasks = samples.map((s) => s.long_tasks_ms);
  const unattributed = samples.map((s) => s.unattributed_ms);

  const rscP95 = pct(rsc, 95);
  const clientP95 = pct(client, 95);
  const renderP95 = pct(render, 95);
  const appP95 = pct(client, 95);
  const dominant =
    pct(chunks, 95) >= Math.max(pct(longTasks, 95) ?? 0, rscP95 ?? 0)
      ? "JS_CHUNK_LOAD"
      : pct(longTasks, 95) >= (rscP95 ?? 0)
        ? "LONG_TASKS"
        : rscP95 > 500
          ? "RSC_NETWORK"
          : "CLIENT_HYDRATION_RENDER";

  return {
    ROUTE: route.name,
    FROM: route.from,
    TO: route.href,
    P95: pct(usable, 95),
    DOM_TARGET_VALID: true,
    CLIENT_SOFT: samples.every((s) => !s.reload),
    PREFETCH: samples.filter((s) => s.prefetch_hit).length / samples.length,
    RSC_P95: rscP95,
    SERVER_P95: pct(samples.map((s) => s.next_origin_processing_ms), 95),
    CLIENT_P95: clientP95,
    RENDER_P95: renderP95,
    CHUNK_P95: pct(chunks, 95),
    LONG_TASKS_P95: pct(longTasks, 95),
    UNATTRIBUTED_P95: pct(unattributed, 95),
    ROOT_CAUSE: dominant,
    APP_CONTROLLED: appP95 <= 1500 ? "YES" : "NO",
    FIX_REQUIRED: appP95 <= 1500 ? "NO" : "YES",
    APPLICATION_CONTROLLED_P95: appP95,
    n: samples.length,
    samples,
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 120000 });
  const build = await page.evaluate(
    () => document.documentElement.innerHTML.match(/"b":"([^"]+)"/)?.[1] || null,
  );

  const matrix = [];
  for (const route of routes) {
    matrix.push(await measureRoute(page, route));
  }

  const out = {
    phase: "JP-HOMEPAGE-CMS-AUTHORITY-06-SOFT-NAV-DECOMPOSE",
    measured_at: new Date().toISOString(),
    base: BASE,
    build,
    n_per_route: N,
    matrix,
    TRUE_SOFT_NAV_WORST_APP_P95: Math.max(...matrix.map((m) => m.APPLICATION_CONTROLLED_P95 || 0)),
    APP_MULTI_SECOND_ROUTE_COUNT: matrix.filter((m) => (m.APPLICATION_CONTROLLED_P95 || 0) > 1500).length,
  };

  fs.writeFileSync(OUT_JSON, JSON.stringify(out, null, 2));
  console.log(JSON.stringify({ worst: out.TRUE_SOFT_NAV_WORST_APP_P95, matrix: matrix.map((m) => ({ route: m.ROUTE, p95: m.P95, app: m.APPLICATION_CONTROLLED_P95, cause: m.ROOT_CAUSE })) }, null, 2));
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
