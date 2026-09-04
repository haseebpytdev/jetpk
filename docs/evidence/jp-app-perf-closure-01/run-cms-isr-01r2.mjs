/**
 * JP-APP-PERF-CLOSURE-01R2 — CMS/ISR published-route smoke (read-only).
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const routes = [
  { path: "/", expect: /JetPakistan|Search|Flights/i },
  { path: "/about-us", expect: /About/i },
  { path: "/faq", expect: /FAQ|Frequently/i },
  { path: "/contact", expect: /Contact/i },
  { path: "/support", expect: /Support|Help/i },
  { path: "/privacy", expect: /Privacy/i },
  { path: "/terms", expect: /Terms/i },
  { path: "/groups", expect: /Group|Groups/i },
];

function leakHints(html) {
  const hits = [];
  if (/"status"\s*:\s*"authenticated"/i.test(html)) hits.push("authenticated_status");
  if (/Parwaaz|YoursDomain|YD Travel|haseeb-master/i.test(html)) hits.push("wrong_tenant_brand");
  if (/preview[_-]?token|draft[_-]?mode|unpublished/i.test(html)) hits.push("preview_leak");
  return hits;
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-CMS",
  });
  const page = await ctx.newPage();
  const samples = [];
  let fail = 0;
  for (const r of routes) {
    const res = await page.goto(`${BASE}${r.path}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    const html = await page.content();
    const text = await page.locator("body").innerText().catch(() => "");
    const leaks = leakHints(html);
    const ok =
      (res?.status() || 0) < 400 &&
      r.expect.test(text) &&
      leaks.length === 0 &&
      !/Access denied|Something went wrong/i.test(text);
    if (!ok) fail += 1;
    samples.push({
      path: r.path,
      status: res?.status() ?? null,
      cache: res?.headers()?.["x-nextjs-cache"] || null,
      prerender: res?.headers()?.["x-nextjs-prerender"] || null,
      ok,
      leaks,
      title_snip: text.slice(0, 80).replace(/\s+/g, " "),
    });
  }
  // Preview route should still resolve without exposing as published home
  let preview_ok = "SKIP";
  try {
    const res = await page.goto(`${BASE}/preview`, { waitUntil: "domcontentloaded", timeout: 30000 });
    const text = await page.locator("body").innerText().catch(() => "");
    preview_ok =
      (res?.status() || 0) === 404 ||
      /preview|token|unauthorized|not found|access/i.test(text) ||
      page.url().includes("login")
        ? "PROTECTED_OR_MISSING"
        : "OPEN_CHECK";
  } catch {
    preview_ok = "ERROR";
  }

  const out = {
    phase: "JP-APP-PERF-CLOSURE-01R2",
    kind: "cms_isr_regression",
    measured_at: new Date().toISOString(),
    fail,
    preview_ok,
    CMS_ISR_REGRESSION: fail === 0 ? "PASS" : "FAIL",
    samples,
  };
  fs.writeFileSync(path.join(__dirname, "cms-isr-01r2.json"), JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
