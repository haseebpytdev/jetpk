/**
 * Live probe: staff deposits direct URL + dashboard deposits API (read-only).
 * Usage: node dashboard/scripts/jp-dash-final-11/probe-staff-deposits-negative-rbac.mjs
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../..");
const BASE_URL = "https://jetpakistan.pk";
const storagePath = path.join(repoRoot, "tmp/jp-final-11-staff-storage-state.json");
const DEPOSITS_API = `${BASE_URL}/laravel/api/dashboard/deposits`;
const SESSION_API = `${BASE_URL}/laravel/api/dashboard/session`;

async function main() {
  if (!fs.existsSync(storagePath)) {
    console.error(`MISSING_STORAGE_STATE=${storagePath}`);
    process.exit(2);
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: storagePath });
  const apiResp = await context.request.get(DEPOSITS_API, {
    headers: { Accept: "application/json" },
  });
  const sessionResp = await context.request.get(SESSION_API, {
    headers: { Accept: "application/json" },
  });

  const sessionJson = sessionResp.ok() ? await sessionResp.json().catch(() => null) : null;
  const nav = sessionJson?.data?.navigation ?? sessionJson?.navigation ?? [];
  const navHrefs = Array.isArray(nav) ? nav.map((item) => item?.href ?? item?.url ?? "").filter(Boolean) : [];
  const caps = sessionJson?.data?.capabilities ?? sessionJson?.capabilities ?? {};
  const canReview = Boolean(caps?.can_review_deposit ?? caps?.canReviewDeposit);

  const page = await context.newPage();
  const rscErrors = [];
  page.on("pageerror", (error) => rscErrors.push(error.message));

  const response = await page.goto(`${BASE_URL}/staff/dashboard/deposits`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });

  await page.waitForTimeout(2500);
  const accessDenied = (await page.getByTestId("dashboard-access-denied").count()) > 0;
  const depositsWorkspace = (await page.getByTestId("deposits-workspace").count()) > 0;
  const bodyText = await page.locator("body").innerText().catch(() => "");
  const rscDigest = /Server Components render|digest/i.test(bodyText) || rscErrors.some((m) => /Server Components render/i.test(m));

  console.log(
    JSON.stringify(
      {
        PAGE_URL: page.url(),
        PAGE_HTTP_STATUS: response?.status() ?? 0,
        STAFF_DEPOSITS_API_STATUS: apiResp.status(),
        STAFF_DEPOSITS_NAV_VISIBLE: navHrefs.some((h) => String(h).includes("/deposits")) ? "YES" : "NO",
        STAFF_CAN_REVIEW_DEPOSIT: canReview ? "YES" : "NO",
        ACCESS_DENIED_UI: accessDenied ? "YES" : "NO",
        DEPOSITS_WORKSPACE_VISIBLE: depositsWorkspace ? "YES" : "NO",
        STAFF_DIRECT_DEPOSITS_RSC_ERROR: rscDigest ? "YES" : "NO",
        CONSOLE_ERRORS: rscErrors.slice(0, 5),
      },
      null,
      2,
    ),
  );

  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
