/**
 * Read-only production Phase-A media regression check (no supplier search).
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "phase-a-media-prod.json");
const PROD = process.env.JP_01B_PHASE_A_URL ?? "https://jetpakistan.pk";

function isDerivative(url) {
  if (!url) return false;
  const u = url.toLowerCase();
  if (/original\./.test(u)) return false;
  return (
    /\.(avif|webp)(\?|$)/.test(u) ||
    /\/lcp\//.test(u) ||
    /hero-(mobile|tablet|desktop)/.test(u) ||
    /card-\d+/.test(u) ||
    /responsive|derivative/.test(u)
  );
}

function isGiantOriginal(url) {
  return /original\./i.test(url || "");
}

async function pictureCurrentSrc(page, rootSelector) {
  return page.locator(rootSelector).first().evaluate((root) => {
    const img =
      root.querySelector("picture img") ||
      root.querySelector("img") ||
      (root.tagName === "IMG" ? root : null);
    return img ? img.currentSrc || img.src || "" : "";
  }).catch(() => "");
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(PROD + "/", { waitUntil: "networkidle", timeout: 120000 });

await page.locator('[data-testid="homepage-public-hero"]').waitFor({ state: "attached", timeout: 20000 });
await page.locator('[data-testid="route-card"]').first().scrollIntoViewIfNeeded().catch(() => null);
await page.locator('[data-testid="destination-card"]').first().scrollIntoViewIfNeeded().catch(() => null);
await page.locator('[data-testid="featured-offer-card"]').first().scrollIntoViewIfNeeded().catch(() => null);

const hero = await pictureCurrentSrc(page, '[data-testid="homepage-hero-responsive-picture"]');
const route = await pictureCurrentSrc(page, '[data-testid="route-card"]');
const destination = await pictureCurrentSrc(page, '[data-testid="destination-card"]');
const featured = await pictureCurrentSrc(page, '[data-testid="featured-offer-card"]');

const result = {
  url: PROD,
  hero,
  route,
  destination,
  featured,
  HERO_CURRENT_SRC_IS_DERIVATIVE: isDerivative(hero),
  ROUTE_CURRENT_SRC_IS_DERIVATIVE: isDerivative(route),
  DESTINATION_CURRENT_SRC_IS_DERIVATIVE: isDerivative(destination),
  FEATURED_CURRENT_SRC_IS_DERIVATIVE: isDerivative(featured),
  GIANT_ORIGINAL_SELECTED_WHEN_DERIVATIVE_AVAILABLE: [hero, route, destination, featured]
    .filter(Boolean)
    .some(isGiantOriginal),
};

fs.writeFileSync(OUT, JSON.stringify(result, null, 2));
console.log(JSON.stringify(result, null, 2));
await browser.close();
