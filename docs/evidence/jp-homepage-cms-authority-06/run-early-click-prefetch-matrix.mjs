/**
 * Authority-06 R2 — early-click delay × route timing matrix (production).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const DELAYS = [0, 100, 250, 500, 750, 1000, 1500, 2000, 3000].map(Number);
const N = Number(process.env.JP_MATRIX_N || 10);
const OUT_JSON = path.join(__dirname, "early-click-prefetch-matrix.json");
const OUT_MD = path.join(__dirname, "early-click-prefetch-matrix.md");

const ROUTES = [
  {
    name: "home_to_login",
    href: "/login",
    click: async (page) => {
      const cta = page.getByTestId("header-login-cta");
      if (await cta.isVisible()) return cta.click({ timeout: 8000 });
      return page.getByRole("banner").getByRole("link", { name: /^login$/i }).click({ timeout: 8000 });
    },
    usable: 'input[type="password"], form',
  },
  {
    name: "home_to_groups",
    href: "/groups",
    click: async (page) =>
      page.getByRole("navigation", { name: "Primary" }).getByRole("link", { name: /^groups$/i }).click({ timeout: 8000 }),
    usable: '[data-testid="groups-landing-page"], main h1',
  },
  {
    name: "home_to_about",
    href: "/about-us",
    click: async (page) => page.locator('a[href="/about-us"]').first().click({ timeout: 8000, force: true }),
    usable: "main h1",
  },
];

function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

function normPath(href) {
  return (href || "/").replace(/\/$/, "") || "/";
}

function rscKey(url) {
  try {
    const u = new URL(url);
    if (!u.pathname.includes("_rsc") && !/[?&]_rsc=/.test(url)) return null;
    return `${u.pathname}${u.searchParams.get("_rsc") ? `?_rsc=${u.searchParams.get("_rsc")}` : ""}`;
  } catch {
    return null;
  }
}

function isLoginRsc(url) {
  return /\/login(\?|$)/.test(url) && (url.includes("_rsc") || url.includes("/login?"));
}

async function oneSample(page, route, delayMs, sampleIndex) {
  const events = [];
  const onReq = (req) => {
    const u = req.url();
    if (!u.includes("jetpakistan") && !u.startsWith(BASE)) return;
    if (!/_rsc=|\/login|\/groups|\/about-us/.test(u)) return;
    events.push({
      phase: "req",
      t: Date.now(),
      method: req.method(),
      url: u.slice(0, 240),
      resourceType: req.resourceType(),
    });
  };
  const onRes = async (res) => {
    const u = res.url();
    if (!/_rsc=|\/login|\/groups|\/about-us/.test(u)) return;
    const h = await res.allHeaders().catch(() => ({}));
    const timing = res.request().timing();
    events.push({
      phase: "res",
      t: Date.now(),
      status: res.status(),
      url: u.slice(0, 240),
      cacheControl: h["cache-control"] ?? null,
      vary: h.vary ?? null,
      nextCache: h["x-nextjs-cache"] ?? null,
      age: h.age ?? null,
      startTime: timing?.startTime ?? null,
      responseEnd: timing?.responseEnd ?? null,
    });
  };

  await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 120000 });
  const homeReady = Date.now();
  await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 25000 }).catch(() => {});
  const hydratedAt = Date.now();

  page.on("request", onReq);
  page.on("response", onRes);

  if (delayMs > 0) await page.waitForTimeout(delayMs);

  const clickTime = Date.now();
  await route.click(page);

  const want = normPath(route.href);
  await page
    .waitForFunction((w) => normPath(location.pathname) === w, want, { timeout: 20000 })
    .catch(() => null);
  const routeCommit = Date.now();

  await page.waitForSelector(route.usable, { timeout: 20000 }).catch(() => null);
  const targetUsable = Date.now();

  page.off("request", onReq);
  page.off("response", onRes);

  const beforeClick = events.filter((e) => e.t < clickTime);
  const afterClick = events.filter((e) => e.t >= clickTime);

  const prefetchRsc = beforeClick.filter((e) => e.phase === "res" && e.url.includes(route.href.replace(/^\//, "")) && e.url.includes("_rsc"));
  const navRscRes = afterClick.filter((e) => e.phase === "res" && e.url.includes("_rsc"));
  const navRscReq = afterClick.filter((e) => e.phase === "req" && e.url.includes("_rsc"));

  const firstNavRes = navRscRes[0];
  const rscStart = navRscReq[0]?.t ?? firstNavRes?.t ?? null;
  const rscEnd = firstNavRes?.t ?? null;
  const rscTtfb =
    firstNavRes?.responseEnd != null && firstNavRes?.startTime != null
      ? Math.max(0, firstNavRes.responseEnd - firstNavRes.startTime)
      : rscStart && rscEnd
        ? rscEnd - rscStart
        : null;

  const prefetchUrl = prefetchRsc.at(-1)?.url ?? null;
  const navUrl = firstNavRes?.url ?? navRscReq[0]?.url ?? null;
  const sameVariant = prefetchUrl && navUrl ? prefetchUrl.split("?")[0] === navUrl.split("?")[0] : false;

  return {
    route: route.name,
    delay_ms: delayMs,
    sample: sampleIndex,
    HOME_READY_TIME: homeReady,
    HYDRATED_TIME: hydratedAt,
    CLICK_TIME: clickTime,
    PREFETCH_START_TIME: beforeClick.find((e) => e.phase === "req" && e.url.includes("_rsc"))?.t ?? null,
    PREFETCH_END_TIME: prefetchRsc.at(-1)?.t ?? null,
    PREFETCH_COMPLETED_BEFORE_CLICK: prefetchRsc.length > 0,
    CLICK_REUSED_PREFETCH: prefetchRsc.length > 0 && navRscRes.length === 0,
    SECOND_RSC_REQUEST: navRscRes.length > 1 || navRscReq.length > 1,
    RSC_REQUEST_START: rscStart,
    RSC_TTFB: rscTtfb,
    RSC_END: rscEnd,
    ROUTER_TRANSITION_START: clickTime,
    ROUTER_TRANSITION_END: routeCommit,
    TARGET_USABLE: targetUsable,
    CLICK_TO_RSC_START: rscStart ? rscStart - clickTime : null,
    RSC_DOWNLOAD: rscEnd && rscStart ? rscEnd - rscStart : null,
    RSC_END_TO_ROUTE_COMMIT: rscEnd ? routeCommit - rscEnd : null,
    ROUTE_COMMIT_TO_USABLE: targetUsable - routeCommit,
    TOTAL_USABLE_MS: targetUsable - clickTime,
    PREFETCH_RSC_REQUEST: prefetchUrl,
    NAVIGATION_RSC_REQUEST: navUrl,
    SAME_URL_OR_VARIANT: sameVariant,
    PREFETCH_RESPONSE_CACHEABLE: /s-maxage|public|max-age/i.test(String(prefetchRsc.at(-1)?.cacheControl ?? "")),
    NAVIGATION_REUSED_RESPONSE: prefetchRsc.length > 0 && navRscRes.length === 0 ? "YES" : navRscRes.some((n) => n.nextCache === "HIT" || n.nextCache === "STALE") ? "PARTIAL" : "NO",
    DUPLICATE_RSC_REQUEST: navRscReq.length > 1 ? "YES" : "NO",
    nextCache: firstNavRes?.nextCache ?? null,
  };
}

function summarizeBucket(samples) {
  const usable = samples.map((s) => s.TOTAL_USABLE_MS);
  return {
    n: samples.length,
    USABLE_P50: pct(usable, 50),
    USABLE_P95: pct(usable, 95),
    PREFETCH_BEFORE_CLICK_RATE: samples.filter((s) => s.PREFETCH_COMPLETED_BEFORE_CLICK).length / samples.length,
    REUSE_RATE: samples.filter((s) => s.NAVIGATION_REUSED_RESPONSE === "YES").length / samples.length,
    DUPLICATE_RSC_RATE: samples.filter((s) => s.DUPLICATE_RSC_REQUEST === "YES").length / samples.length,
    MEDIAN_CLICK_TO_RSC_START: pct(samples.map((s) => s.CLICK_TO_RSC_START), 50),
    MEDIAN_RSC_TTFB: pct(samples.map((s) => s.RSC_TTFB), 50),
    MEDIAN_RSC_END_TO_ROUTE_COMMIT: pct(samples.map((s) => s.RSC_END_TO_ROUTE_COMMIT), 50),
    MEDIAN_ROUTE_COMMIT_TO_USABLE: pct(samples.map((s) => s.ROUTE_COMMIT_TO_USABLE), 50),
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const allSamples = [];

  for (const route of ROUTES) {
    for (const delay of DELAYS) {
      for (let i = 0; i < N; i += 1) {
        allSamples.push(await oneSample(page, route, delay, i));
        await page.waitForTimeout(150);
      }
    }
  }

  const buckets = [];
  for (const route of ROUTES) {
    for (const delay of DELAYS) {
      const subset = allSamples.filter((s) => s.route === route.name && s.delay_ms === delay);
      buckets.push({ route: route.name, delay_ms: delay, ...summarizeBucket(subset) });
    }
  }

  const thresholdByRoute = {};
  for (const route of ROUTES) {
    const routeBuckets = buckets.filter((b) => b.route === route.name && b.USABLE_P95 != null);
    const fast = routeBuckets.find((b) => b.USABLE_P95 <= 1500);
    thresholdByRoute[route.name] = fast?.delay_ms ?? null;
  }

  const out = {
    captured_at: new Date().toISOString(),
    base: BASE,
    production_sha: "2c521c73ba18fba0947bb1a27e738c3c0602a792",
    public_build_id: "cDqLQnU11t6QnUKL1LNaK",
    delays_ms: DELAYS,
    n_per_bucket: N,
    samples: allSamples,
    buckets,
    EARLY_CLICK_THRESHOLD_MS: thresholdByRoute,
    PREFETCH_REUSE_ROOT_CAUSE: "PENDING_ANALYSIS",
  };

  const reuseYes = allSamples.filter((s) => s.NAVIGATION_REUSED_RESPONSE === "YES").length;
  const dupYes = allSamples.filter((s) => s.DUPLICATE_RSC_REQUEST === "YES").length;
  if (reuseYes === 0 && dupYes > 0) {
    out.PREFETCH_REUSE_ROOT_CAUSE =
      "Prefetch RSC completes but navigation issues separate _rsc variant/request; router does not skip second fetch.";
  } else if (allSamples.filter((s) => !s.PREFETCH_COMPLETED_BEFORE_CLICK && s.delay_ms <= 500).length > N) {
    out.PREFETCH_REUSE_ROOT_CAUSE =
      "Priority prefetch starts post-hydration (useEffect+setTimeout); clicks <500ms race ahead of prefetch completion.";
  }

  fs.writeFileSync(OUT_JSON, JSON.stringify(out, null, 2));

  let md = `# Early-click prefetch matrix\n\n`;
  md += `Captured: ${out.captured_at}\n\n`;
  md += `| Route | Delay ms | P50 usable | P95 usable | Prefetch before click | Reuse YES rate |\n`;
  md += `|-------|----------|------------|------------|----------------------|----------------|\n`;
  for (const b of buckets) {
    md += `| ${b.route} | ${b.delay_ms} | ${b.USABLE_P50} | ${b.USABLE_P95} | ${(b.PREFETCH_BEFORE_CLICK_RATE * 100).toFixed(0)}% | ${(b.REUSE_RATE * 100).toFixed(0)}% |\n`;
  }
  md += `\n## Threshold (P95<=1500ms)\n\n\`\`\`json\n${JSON.stringify(thresholdByRoute, null, 2)}\n\`\`\`\n`;
  md += `\n## Root cause\n\n${out.PREFETCH_REUSE_ROOT_CAUSE}\n`;
  fs.writeFileSync(OUT_MD, md);

  console.log(JSON.stringify({ thresholds: thresholdByRoute, root: out.PREFETCH_REUSE_ROOT_CAUSE }, null, 2));
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
