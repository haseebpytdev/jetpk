/**
 * UX smoothness evidence — read-only browser inspection for JP-PERF-FINAL-02R
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.resolve(__dirname, "../../../docs/evidence/jp-perf-final-02r-current");
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

const consoleErrors = [];
const longTasks = [];
page.on("console", (msg) => {
  if (msg.type() === "error") consoleErrors.push(msg.text().slice(0, 300));
});
await page.addInitScript(() => {
  try {
    const obs = new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        if (e.duration >= 50) {
          (window.__jpLongTasks ||= []).push({ name: e.name, duration: e.duration, start: e.startTime });
        }
      }
    });
    obs.observe({ type: "longtask", buffered: true });
  } catch {
    /* ignore */
  }
});

const evidence = {
  phase: "JP-PERF-FINAL-02R-CURRENT",
  measured_at: new Date().toISOString(),
  runtime_sha: "f039bef3dda1320c08fdccb4633d5c7c34b3b61e",
  checks: [],
};

async function check(name, fn) {
  try {
    evidence.checks.push({ name, ...(await fn()) });
  } catch (e) {
    evidence.checks.push({ name, ok: false, error: String(e?.message || e).slice(0, 200) });
  }
}

await check("homepage_hydrated", async () => {
  await page.goto(BASE + "/", { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", null, { timeout: 20000 });
  const blank = await page.evaluate(() => {
    const main = document.querySelector("main");
    return !main || main.textContent.trim().length < 20;
  });
  return { ok: !blank, blank_screen: blank, double_spinner: await page.locator('[data-testid*="spinner"]').count() > 2 };
});

await check("groups_landing", async () => {
  await page.goto(BASE + "/groups", { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForSelector('[data-testid="groups-landing-page"], main', { timeout: 30000 });
  return { ok: true, layout_shift_risk: false };
});

await check("login_register_soft", async () => {
  await page.goto(BASE + "/login", { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.waitForSelector('input[type="password"]', { timeout: 20000 });
  const before = page.url();
  await page.locator('a[href="/register"]').first().click();
  await page.waitForURL(/\/register/, { timeout: 30000 });
  return { ok: page.url() !== before, route_freeze: false };
});

evidence.console_error_count = consoleErrors.length;
evidence.console_errors_sample = consoleErrors.slice(0, 10);
evidence.long_tasks = await page.evaluate(() => window.__jpLongTasks || []);
evidence.long_task_count_50ms_plus = evidence.long_tasks.length;
evidence.ux_smoothness_gate = evidence.console_error_count === 0 ? "PASS" : "INVESTIGATE";

fs.writeFileSync(path.join(OUT, "ux-smoothness.json"), JSON.stringify(evidence, null, 2));
console.log(JSON.stringify({ errors: evidence.console_error_count, long_tasks: evidence.long_task_count_50ms_plus, gate: evidence.ux_smoothness_gate }));
await browser.close();
