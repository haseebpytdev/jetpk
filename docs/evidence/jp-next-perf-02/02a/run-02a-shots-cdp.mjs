/**
 * CDP screenshots — avoid Playwright font-ready hang.
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const shot = path.join(__dirname, "screenshots");
fs.mkdirSync(shot, { recursive: true });

async function capture(page, name) {
  const client = await page.context().newCDPSession(page);
  const { data } = await client.send("Page.captureScreenshot", { format: "png", fromSurface: true });
  fs.writeFileSync(path.join(shot, name), Buffer.from(data, "base64"));
  console.log("ok", name);
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  const jobs = [
    ["groups-loaded.png", "https://jetpakistan.pk/groups/search?sector=ISB-SHJ", null],
    [
      "oneway.png",
      "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
      '[data-testid="flight-result-card"]',
    ],
    [
      "return-paired.png",
      "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
      '[data-testid="pair-return-card"]',
    ],
    [
      "return-segmented.png",
      "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=segmented",
      '[data-testid="outbound-option-card"]',
    ],
  ];

  for (const [name, url, sel] of jobs) {
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000 });
    if (sel) {
      try {
        await page.waitForSelector(sel, { timeout: 90000 });
      } catch {}
    }
    await page.waitForTimeout(900);
    await capture(page, name);
  }

  // Seed booking session for checkout shots
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&_=" +
      Date.now(),
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await page.waitForSelector('[data-testid="flight-result-card"]', { timeout: 100000 });
  await page.locator('[data-testid="book-now-trigger"]').first().click();
  await page.locator('[data-testid="continue-to-passengers"]').first().click({ timeout: 30000 });
  await page.waitForURL(/\/booking\/passengers/, { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForTimeout(1000);
  await capture(page, "traveler-ready.png");
  await page.goto("https://jetpakistan.pk/booking/review", { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(1500);
  await capture(page, "review-ready.png");
  await page.goto("https://jetpakistan.pk/booking/payment/manual", {
    waitUntil: "domcontentloaded",
    timeout: 60000,
  });
  await page.waitForTimeout(1500);
  await capture(page, "payment-shell-ready.png");

  await browser.close();
  console.log("SHOTS_DONE");
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
