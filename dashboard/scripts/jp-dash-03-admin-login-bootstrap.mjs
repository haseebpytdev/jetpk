/**
 * One-time headed login bootstrap for JP-DASH-03 production Admin acceptance.
 * Saves authenticated storageState locally — never commit the output file.
 *
 * Usage: node scripts/jp-dash-03-admin-login-bootstrap.mjs
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../");
const storagePath = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");

const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const loginUrl = `${baseUrl}/login`;
const dashboardPattern = /\/admin\/dashboard/;

async function main() {
  fs.mkdirSync(path.dirname(storagePath), { recursive: true });

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log("Open the headed browser and complete Platform Admin login (including OTP if required).");
  console.log("Waiting for authenticated /admin/dashboard …");

  await page.goto(loginUrl, { waitUntil: "domcontentloaded", timeout: 120_000 });

  await page.waitForURL(dashboardPattern, { timeout: 600_000 });
  await page.waitForSelector("[data-testid='dashboard-portal-label']", { timeout: 120_000 });

  const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
  if (!portalLabel || /preview/i.test(portalLabel)) {
    await browser.close();
    console.error("ADMIN_PLAYWRIGHT_SESSION=FAIL — dashboard did not show authenticated Admin console.");
    process.exit(1);
  }

  await context.storageState({ path: storagePath });
  await browser.close();

  console.log("ADMIN_PLAYWRIGHT_SESSION=READY");
}

main().catch((error) => {
  console.error("ADMIN_PLAYWRIGHT_SESSION=FAIL");
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
