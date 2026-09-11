/**
 * Authority-06 R3 — CDP performance trace for slow early-click navigations.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const TARGET_SLOW = Number(process.env.JP_TRACE_SLOW_MIN_MS || 1200);
const MAX_TRACES = Number(process.env.JP_TRACE_MAX || 12);
const OUT_DIR = path.join(__dirname, "router-wait-traces");
const OUT_MD = path.join(__dirname, "router-wait-trace.md");

const ROUTES = [
  { name: "home_to_login", href: "/login", testid: "header-login-cta", usable: '[data-testid="login-form"] input[type="password"]' },
  {
    name: "home_to_support",
    href: "/support",
    dropdown: { menu: "Support", label: "Help Center" },
    usable: "#support-form-heading",
  },
];

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
  await page.locator(`a[href="${route.href}"]`).first().click({ timeout: 8000, force: true });
}

function analyzeLongTasks(longTasks, intervalStart, intervalEnd) {
  const inInterval = longTasks.filter((t) => t.start >= intervalStart && t.start <= intervalEnd);
  const busy = inInterval.reduce((s, t) => s + t.duration, 0);
  const longest = inInterval.sort((a, b) => b.duration - a.duration)[0] ?? null;
  return { busyMs: Math.round(busy), idleMs: Math.round(Math.max(0, intervalEnd - intervalStart - busy)), longest };
}

async function captureTrace(browser, route, sampleIdx) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await page.addInitScript(() => {
    window.__jpLongTasks = [];
    try {
      const obs = new PerformanceObserver((list) => {
        for (const e of list.getEntries()) {
          window.__jpLongTasks.push({ start: e.startTime, duration: e.duration, name: e.name });
        }
      });
      obs.observe({ type: "longtask", buffered: true });
    } catch {
      /* unsupported */
    }
  });

  let rscEndRelMs = null;
  const clickWall = { ts: null };

  await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});

  await context.tracing.start({ screenshots: true, snapshots: true, sources: true });

  await page.evaluate(() => {
    window.__jpMarks = { click: null, url: null, usable: null };
  });

  clickWall.ts = Date.now();
  page.on("response", (res) => {
    if (/[?&]_rsc=/.test(res.url())) {
      res
        .finished()
        .then(() => {
          rscEndRelMs = Date.now() - clickWall.ts;
        })
        .catch(() => {});
    }
  });
  await page.evaluate(() => {
    window.__jpMarks.click = performance.now();
  });
  await clickNav(page, route);

  await page.waitForFunction(
    (href) => (location.pathname.replace(/\/$/, "") || "/") === href.replace(/\/$/, ""),
    route.href,
    { timeout: 30000 },
  ).catch(() => null);
  await page.evaluate(() => {
    window.__jpMarks.url = performance.now();
  });
  await page.waitForSelector(route.usable, { timeout: 30000, state: "visible" }).catch(() => null);
  await page.evaluate(() => {
    window.__jpMarks.usable = performance.now();
  });

  const tracePath = path.join(OUT_DIR, `${route.name}-slow-${sampleIdx}.zip`);
  fs.mkdirSync(OUT_DIR, { recursive: true });
  await context.tracing.stop({ path: tracePath });

  const probe = await page.evaluate(() => ({
    marks: window.__jpMarks,
    longTasks: window.__jpLongTasks || [],
    resources: performance
      .getEntriesByType("resource")
      .filter((e) => e.startTime >= (window.__jpMarks?.click ?? 0))
      .map((e) => ({
        name: e.name.slice(0, 120),
        initiatorType: e.initiatorType,
        duration: Math.round(e.duration),
        transferSize: e.transferSize,
      })),
  }));

  await context.close();

  const clickPerf = probe.marks.click ?? 0;
  const urlPerf = probe.marks.url ?? clickPerf;
  const usablePerf = probe.marks.usable ?? urlPerf;
  const rscEnd = rscEndRelMs != null ? clickPerf + rscEndRelMs : clickPerf;
  const intervalStart = rscEnd;
  const intervalEnd = urlPerf;
  const lt = analyzeLongTasks(probe.longTasks, intervalStart, intervalEnd);

  const chunkMs = probe.resources
    .filter((r) => r.name.includes("/_next/static/chunks/"))
    .reduce((s, r) => s + r.duration, 0);
  const sessionMs = probe.resources
    .filter((r) => r.name.includes("/auth/session"))
    .reduce((s, r) => s + r.duration, 0);
  const configMs = probe.resources
    .filter((r) => r.name.includes("/content/config"))
    .reduce((s, r) => s + r.duration, 0);
  const rscMs = probe.resources.filter((r) => r.name.includes("_rsc=")).reduce((s, r) => s + r.duration, 0);

  let dominant = "MAIN_THREAD_LONG_TASKS";
  if (sessionMs > lt.busyMs && sessionMs > rscMs) dominant = "SESSION_BOOTSTRAP_NETWORK";
  else if (configMs > lt.busyMs && configMs > rscMs) dominant = "PUBLIC_CONFIG_NETWORK";
  else if (chunkMs > lt.busyMs) dominant = "CHUNK_PARSE_EVAL";
  else if (rscMs > lt.busyMs) dominant = "RSC_NETWORK_CALLBACK";
  else if (lt.busyMs < 100 && intervalEnd - intervalStart > 500) dominant = "MAIN_THREAD_IDLE_WAITING_ON_ROUTER";

  return {
    route: route.name,
    sampleIdx,
    RSC_END: Math.round(rscEnd - clickPerf),
    ROUTE_COMMIT: Math.round(urlPerf - clickPerf),
    INTERVAL_MS: Math.round(urlPerf - rscEnd),
    TOTAL_USABLE_MS: Math.round(usablePerf - clickPerf),
    MAIN_THREAD_BUSY_MS: lt.busyMs,
    MAIN_THREAD_IDLE_MS: lt.idleMs,
    DOM_WORK_MS: null,
    REACT_WORK_MS: null,
    NETWORK_CALLBACK_MS: sessionMs + configMs + rscMs,
    LONGEST_TASK_MS: lt.longest ? Math.round(lt.longest.duration) : 0,
    DOMINANT_CAUSE: dominant,
    WHAT_WAS_BROWSER_DOING: `${dominant}; longTasks=${lt.busyMs}ms sessionNet=${sessionMs}ms configNet=${configMs}ms rscNet=${rscMs}ms chunks=${chunkMs}ms`,
    trace_file: path.basename(tracePath),
    trace_format: "playwright-zip",
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const traces = [];
  for (const route of ROUTES) {
    let attempts = 0;
    while (traces.filter((t) => t.route === route.name).length < MAX_TRACES / ROUTES.length && attempts < 40) {
      attempts += 1;
      const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
      const page = await context.newPage();
      await page.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
      await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
      const t0 = Date.now();
      await clickNav(page, route);
      await page.waitForSelector(route.usable, { timeout: 30000, state: "visible" }).catch(() => null);
      const ms = Date.now() - t0;
      await context.close();
      if (ms >= TARGET_SLOW) {
        const trace = await captureTrace(browser, route, traces.length + 1);
        traces.push(trace);
        console.log(`captured ${route.name} slow=${ms}ms interval=${trace.INTERVAL_MS}ms cause=${trace.DOMINANT_CAUSE}`);
      }
    }
  }
  await browser.close();

  const causes = {};
  for (const t of traces) causes[t.DOMINANT_CAUSE] = (causes[t.DOMINANT_CAUSE] || 0) + 1;
  const topCause = Object.entries(causes).sort((a, b) => b[1] - a[1])[0]?.[0] ?? "UNKNOWN";

  const md = [
    "# Router-wait trace analysis (Authority-06 R3)",
    "",
    `Captured: ${new Date().toISOString()}`,
    `Production SHA: 8c50fc61967e51c5b576375b55ae7701d122c121`,
    `Slow threshold: >=${TARGET_SLOW}ms usable`,
    `Samples: ${traces.length}`,
    "",
    `## ROUTER_WAIT_TRACE_DOMINANT_CAUSE`,
    "",
    topCause,
    "",
    "## Representative slow samples",
    "",
    "| route | RSC_END | ROUTE_COMMIT | INTERVAL_MS | MAIN_BUSY | MAIN_IDLE | NET_CB | LONGEST_TASK | DOMINANT |",
    "|-------|---------|--------------|-------------|-----------|-----------|--------|--------------|----------|",
    ...traces.map(
      (t) =>
        `| ${t.route} | ${t.RSC_END} | ${t.ROUTE_COMMIT} | ${t.INTERVAL_MS} | ${t.MAIN_THREAD_BUSY_MS} | ${t.MAIN_THREAD_IDLE_MS} | ${t.NETWORK_CALLBACK_MS} | ${t.LONGEST_TASK_MS} | ${t.DOMINANT_CAUSE} |`,
    ),
    "",
    "## Per-sample notes",
    "",
    ...traces.map((t) => `### ${t.route} #${t.sampleIdx}\n\n- ${t.WHAT_WAS_BROWSER_DOING}\n- trace: \`${t.trace_file}\`\n`),
  ].join("\n");

  fs.writeFileSync(OUT_MD, md);
  fs.writeFileSync(path.join(__dirname, "router-wait-trace.json"), JSON.stringify({ traces, topCause }, null, 2));
  console.log(`topCause=${topCause} traces=${traces.length}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
