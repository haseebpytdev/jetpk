/**
 * Authority-06 — prove prefetch behavior for home -> login (read-only).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const OUT = path.join(__dirname, "soft-nav-prefetch-proof.json");
const WAIT_PREFETCH_MS = Number(process.env.JP_PREFETCH_WAIT_MS || 4500);

function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function oneSample(page, sampleIndex, waitPrefetch) {
  const events = [];
  const onReq = (req) => {
    const u = req.url();
    if (/[?&]_rsc=/.test(u) || u.includes("/login")) {
      events.push({ t: Date.now(), kind: "req", method: req.method(), u: u.slice(0, 200) });
    }
  };
  const onRes = async (res) => {
    const u = res.url();
    if (!/[?&]_rsc=/.test(u) && !u.includes("/login")) return;
    const h = await res.allHeaders().catch(() => ({}));
    events.push({
      t: Date.now(),
      kind: "res",
      status: res.status(),
      u: u.slice(0, 200),
      cacheControl: h["cache-control"] ?? null,
      age: h.age ?? null,
      vary: h.vary ?? null,
      nextCache: h["x-nextjs-cache"] ?? null,
      serverTiming: h["server-timing"]?.slice(0, 120) ?? null,
      fromServiceWorker: res.fromServiceWorker(),
    });
  };

  await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 }).catch(() => {});
  await page.waitForTimeout(300);

  const mountAt = Date.now();
  page.on("request", onReq);
  page.on("response", onRes);

  if (waitPrefetch) await page.waitForTimeout(WAIT_PREFETCH_MS);

  const prefetchBeforeClick = events.filter((e) => e.kind === "res" && /login/.test(e.u));
  const clickAt = Date.now();
  const cta = page.getByTestId("header-login-cta");
  if (await cta.count()) await cta.click({ timeout: 10000 });
  else await page.locator('a[href="/login"]').first().click({ timeout: 10000 });

  await page.waitForFunction(() => location.pathname === "/login", null, { timeout: 15000 }).catch(() => null);
  const urlAt = Date.now();
  await page.waitForSelector('input[type="password"], form', { timeout: 20000 }).catch(() => null);
  const usableAt = Date.now();

  page.off("request", onReq);
  page.off("response", onRes);

  const afterClick = events.filter((e) => e.t >= clickAt);
  const rscAfter = afterClick.filter((e) => e.kind === "res" && /[?&]_rsc=/.test(e.u));
  const prefetchReuse =
    prefetchBeforeClick.length > 0 &&
    rscAfter.length > 0 &&
    prefetchBeforeClick.some((p) => rscAfter.some((a) => a.u.split("?")[0] === p.u.split("?")[0]));

  return {
    sample: sampleIndex,
    wait_prefetch_ms: waitPrefetch ? WAIT_PREFETCH_MS : 0,
    PREFETCH_TRIGGERED: prefetchBeforeClick.length > 0,
    PREFETCH_RSC_COUNT_BEFORE_CLICK: prefetchBeforeClick.length,
    PREFETCH_COMPLETED_BEFORE_CLICK: prefetchBeforeClick.some((e) => e.status === 200),
    CLICK_REUSED_PREFETCH: prefetchReuse,
    SECOND_RSC_AFTER_CLICK: rscAfter.length,
    RSC_CACHE_STATUS: rscAfter[0]?.nextCache ?? rscAfter[0]?.cacheControl ?? null,
    CLICK_TO_USABLE_MS: usableAt - clickAt,
    ROUTER_WAIT_MS: urlAt - clickAt,
    events: events.slice(-12),
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();

  const early = [];
  const warmed = [];
  for (let i = 0; i < 3; i += 1) early.push(await oneSample(page, i, false));
  for (let i = 0; i < 3; i += 1) warmed.push(await oneSample(page, i + 3, true));

  const out = {
    captured_at: new Date().toISOString(),
    base: BASE,
    early_click_no_prefetch_wait: early,
    after_idle_prefetch_wait: warmed,
    PREFETCH_HIT_RATE_BEFORE: early.filter((s) => s.CLICK_REUSED_PREFETCH).length / early.length,
    PREFETCH_HIT_RATE_AFTER_WAIT: warmed.filter((s) => s.CLICK_REUSED_PREFETCH).length / warmed.length,
    WHY_PREFETCH_HIT_ZERO:
      early.every((s) => !s.PREFETCH_COMPLETED_BEFORE_CLICK)
        ? "Click occurs before PublicRoutePrefetch idle queue (2500ms+) completes; no warm RSC at click"
        : "Prefetch completed but navigation RSC not reused",
    EARLY_USABLE_P95: pct(early.map((s) => s.CLICK_TO_USABLE_MS), 95),
    WARMED_USABLE_P95: pct(warmed.map((s) => s.CLICK_TO_USABLE_MS), 95),
  };

  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
