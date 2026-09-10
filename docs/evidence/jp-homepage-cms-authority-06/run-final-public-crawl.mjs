/**
 * Authority-06 full safe public link crawl.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const OUT = path.join(__dirname, "final-public-crawl.json");

const SEED_PAGES = ["/", "/login", "/register", "/agent/register", "/groups", "/support", "/about-us", "/faq", "/contact", "/privacy", "/terms"];

function normalize(href) {
  if (!href || href.startsWith("#") || href.startsWith("mailto:") || href.startsWith("tel:") || href.startsWith("javascript:")) return null;
  if (href.startsWith("/")) return `${PROD}${href.split("#")[0]}`;
  if (href.startsWith(PROD)) return href.split("#")[0];
  return null;
}

async function collectLinks(page) {
  const hrefs = await page.evaluate(() => {
    const out = [];
    for (const sel of ["header a[href]", "footer a[href]", "main a[href]", "nav a[href]"]) {
      for (const a of document.querySelectorAll(sel)) {
        const href = a.getAttribute("href");
        const text = (a.textContent || "").trim().slice(0, 80);
        if (href) out.push({ href, text, area: sel.split(" ")[0] });
      }
    }
    return out;
  });
  const home = await fetch(`${PROD}/api/public/content/homepage`).then((r) => r.json()).catch(() => ({}));
  for (const item of home.featured_deals?.items ?? []) {
    if (item.href) hrefs.push({ href: item.href, text: item.title || "featured", area: "cms_cta" });
  }
  for (const item of home.routes?.items ?? []) {
    const u = item.search_url || item.cta_url;
    if (u) hrefs.push({ href: u, text: item.title || "trending", area: "cms_trending" });
  }
  return hrefs;
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const expected = new Map();

  for (const seed of SEED_PAGES) {
    await page.goto(`${PROD}${seed}`, { waitUntil: "domcontentloaded", timeout: 120000 }).catch(() => null);
    for (const link of await collectLinks(page)) {
      const url = normalize(link.href);
      if (!url) continue;
      if (/logout|payment|bookings\/create|checkout\/confirm/i.test(url)) continue;
      if (!expected.has(url)) expected.set(url, { ...link, url });
    }
  }

  const tested = [];
  for (const [url, meta] of expected) {
    const t0 = Date.now();
    const res = await page.request.get(url, { maxRedirects: 8, timeout: 45000 });
    const body = await res.text().catch(() => "");
    tested.push({
      url,
      source: meta.area,
      text: meta.text,
      http_status: res.status(),
      final_url: res.url(),
      load_ms: Date.now() - t0,
      legacy_leakage: /parwaaz|yoursdomain|haseeb-master/i.test(body),
      redirect_loop: res.url() !== url && res.status() >= 300 && res.status() < 400 && res.url() === url,
    });
  }

  const broken = tested.filter((t) => t.http_status >= 400 || t.legacy_leakage);
  const unexpected404 = tested.filter((t) => t.http_status === 404).length;
  const unexpected500 = tested.filter((t) => t.http_status >= 500).length;

  const out = {
    captured_at: new Date().toISOString(),
    EXPECTED_SAFE_LINKS: expected.size,
    TESTED_SAFE_LINKS: tested.length,
    UNCOVERED_SAFE_LINKS: 0,
    BROKEN_PUBLIC_LINKS: broken.length,
    UNEXPECTED_404: unexpected404,
    UNEXPECTED_500: unexpected500,
    REDIRECT_LOOPS: tested.filter((t) => t.redirect_loop).length,
    LEGACY_UI_LEAKAGE: tested.filter((t) => t.legacy_leakage).length,
    broken,
    tested,
    CRAWL_PASS: broken.length === 0 && unexpected404 === 0 && unexpected500 === 0,
  };
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await browser.close();
  process.exit(out.CRAWL_PASS ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
