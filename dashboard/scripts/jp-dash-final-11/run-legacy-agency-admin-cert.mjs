/**
 * Legacy agency_admin certification (notice + admin denial probes).
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import { FINAL11_AUTH_ROLES, BASE_URL, storagePathForRole } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const ADMIN_PATHS = [
  "/admin/dashboard",
  "/admin/dashboard/users",
  "/admin/dashboard/api-connections",
  "/admin/dashboard/system/go-live",
];

const ADMIN_API_PATHS = [
  "/admin/dashboard/bookings?format=json",
  "/admin/dashboard/users?format=json",
];

async function probe(context, url) {
  const response = await context.request.get(`${BASE_URL}${url}`, {
    headers: { Accept: "application/json,text/html" },
  });
  return response.status();
}

async function main() {
  const storage = storagePathForRole("legacy_agency_admin");
  if (!fs.existsSync(storage)) {
    throw new Error("MISSING_STORAGE:legacy_agency_admin");
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: storage });
  const page = await context.newPage();
  await page.goto(`${BASE_URL}/account/legacy`, { waitUntil: "domcontentloaded", timeout: 60000 });
  const onLegacy =
    page.url().includes("/account/legacy") ||
    /legacy account|no longer supported/i.test(await page.locator("body").innerText().catch(() => ""));
  console.log(`LEGACY_AGENCY_ADMIN_NOTICE=${onLegacy ? "PASS" : "FAIL"}`);

  let adminDenied = 0;
  for (const path of ADMIN_PATHS) {
    const status = await probe(context, path);
    const pageProbe = await context.newPage();
    await pageProbe.goto(`${BASE_URL}${path}`, { waitUntil: "domcontentloaded", timeout: 60000 });
    const denied =
      status === 403 ||
      status === 404 ||
      pageProbe.url().includes("/login") ||
      (await pageProbe.getByTestId("dashboard-access-denied").count()) > 0 ||
      pageProbe.url().includes("/account/legacy");
    await pageProbe.close();
    if (denied) adminDenied += 1;
  }

  let apiEscalation = 0;
  for (const apiPath of ADMIN_API_PATHS) {
    const response = await context.request.get(`${BASE_URL}${apiPath}`, {
      headers: { Accept: "application/json" },
    });
    const status = response.status();
    const contentType = response.headers()["content-type"] || "";
    if (status === 200 && contentType.includes("application/json")) {
      const body = await response.json().catch(() => ({}));
      if (body?.ok === true) apiEscalation += 1;
    }
  }

  await browser.close();

  console.log(`LEGACY_AGENCY_ADMIN_ADMIN_ACCESS=${adminDenied === ADMIN_PATHS.length ? "DENIED" : "FAIL"}`);
  console.log(`LEGACY_AGENCY_ADMIN_USERS_ACCESS=${adminDenied >= 2 ? "DENIED" : "FAIL"}`);
  console.log(`LEGACY_AGENCY_ADMIN_API_CONNECTIONS_ACCESS=${adminDenied >= 3 ? "DENIED" : "FAIL"}`);
  console.log(`LEGACY_AGENCY_ADMIN_SYSTEM_ACCESS=${adminDenied === ADMIN_PATHS.length ? "DENIED" : "FAIL"}`);
  console.log(`LEGACY_AGENCY_ADMIN_API_ESCALATION=${apiEscalation}`);
}

main().catch((err) => {
  console.error(String(err?.message || err));
  process.exit(1);
});
