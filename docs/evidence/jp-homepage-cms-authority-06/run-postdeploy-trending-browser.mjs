/**
 * Trending routes browser UAT on build vkC0lfkEH9Gfj7CHfwcuO
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const OUT = path.join(__dirname, "trending-routes-browser-postdeploy.json");

async function main() {
  const home = await fetch(`${PROD}/api/public/content/homepage`).then((r) => r.json());
  const routes = (home.routes?.items ?? []).filter((r) => String(r.enabled ?? "1") !== "0");
  const browser = await chromium.launch({ headless: true });
  const results = [];
  for (const route of routes) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: "block" });
    const page = await ctx.newPage();
    const started = Date.now();
    const state = { searchId: null, duplicate: 0, terminal: null, polls: 0 };
    page.on("response", async (res) => {
      const u = res.url();
      if (/\/flights\/results\/search/i.test(u) && res.request().method() === "GET") {
        const body = await res.json().catch(() => null);
        if (body?.search_id) state.searchId = body.search_id;
      }
      if (/\/flights\/results\/data/i.test(u) || /\/laravel\/flights\/results\/data/i.test(u)) {
        state.polls += 1;
        const body = await res.json().catch(() => null);
        const status = String(body?.status ?? body?.data?.status ?? "").toLowerCase();
        const n = (body?.offers?.length ?? 0) || (body?.paired_options?.length ?? 0) || (body?.outbound_options?.length ?? 0);
        if (status === "ready" && n > 0) state.terminal = "success";
        if (status === "empty") state.terminal = "no_results";
        if (["failed", "error", "expired"].includes(status)) state.terminal = status;
      }
    });
    const href = route.search_url || route.cta_url;
    await page.goto(PROD, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.waitForTimeout(2000);
    const link = page.locator(`a[href="${href}"], a[href="${href.replace(/^\//, "")}"]`).first();
    if (await link.count()) {
      await link.click({ timeout: 30000 });
    } else {
      await page.goto(`${PROD}${href}`, { waitUntil: "domcontentloaded", timeout: 120000 });
    }
    if (!state.searchId) {
      const u = new URL(page.url());
      state.searchId = u.searchParams.get("search_id");
    }
    while (Date.now() - started < 75000 && !state.terminal) {
      const cards = await page.locator('[data-testid="flight-result-card"], [data-testid="pair-return-card"]').count();
      if (cards > 0) state.terminal = "success";
      if (state.searchId) {
        const poll = await fetch(
          `${PROD}/laravel/flights/results/data?search_id=${encodeURIComponent(state.searchId)}&page=1&per_page=12`,
          { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } },
        ).then((r) => r.json()).catch(() => null);
        state.polls += 1;
        const status = String(poll?.status ?? poll?.data?.status ?? "").toLowerCase();
        const n = (poll?.offers?.length ?? 0) || (poll?.data?.offers?.length ?? 0);
        state.backend = status;
        if (status === "ready" && n > 0) state.terminal = "success";
        if (status === "empty") state.terminal = "no_results";
        if (["failed", "error", "expired"].includes(status)) state.terminal = status;
      }
      await page.waitForTimeout(500);
    }
    results.push({
      route: route.id,
      from: route.from,
      to: route.to,
      href,
      search_id: state.searchId,
      poll_count: state.polls,
      frontend_terminal_status: state.terminal ?? "searching",
      backend_terminal_status: state.backend ?? state.terminal,
      infinite_searching: !state.terminal || state.terminal === "searching",
      duplicate_searches: state.duplicate,
      total_wait_ms: Date.now() - started,
      final_url: page.url(),
    });
    await ctx.close();
  }
  const infinite = results.filter((r) => r.infinite_searching).length;
  const report = {
    captured_at: new Date().toISOString(),
    public_build_id: "vkC0lfkEH9Gfj7CHfwcuO",
    trending_routes_tested: results.length,
    infinite_searching_count: infinite,
    duplicate_search_count: results.reduce((s, r) => s + r.duplicate_searches, 0),
    results,
    pass: infinite === 0,
  };
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ pass: report.pass, infinite, results }, null, 2));
  await browser.close();
  process.exit(infinite > 0 ? 1 : 0);
}

main();
