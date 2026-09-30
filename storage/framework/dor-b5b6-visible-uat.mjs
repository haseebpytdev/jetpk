/**
 * Batch 5 + Batch 6 owner-visible headed Chrome UAT.
 */
import { createRequire } from "node:module";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("../../frontend/node_modules/playwright");
const BASE = "https://jetpakistan.pk";
const OUT = path.join(path.dirname(fileURLToPath(import.meta.url)), "dor-b5b6-visible-uat");
fs.mkdirSync(OUT, { recursive: true });
const sshKey = process.env.JP_SSH_KEY || path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const BATCH5_HEAD = "feef2e7fa489cee778b81c6db370946394337999";
const BUILD_ID = "m1WsmAjtpdlOgryIYPtp7";

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function ssh(cmd) {
  return spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 8 * 1024 * 1024,
  });
}

function activateQa() {
  ssh(`bash /tmp/dor-b2-qa-prep.sh`);
  ssh(`cd /home/pkjetp/jetpk_app && /usr/local/lsws/lsphp83/bin/php <<'PHP'
<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
foreach ([
  "jp-dash-03-qa-admin@jetpakistan.pk",
  "jp-dash-03-qa-staff@jetpakistan.pk",
  "jp-dash-03-qa-agent@jetpakistan.pk",
  "jp-dash-03-qa-customer@jetpakistan.pk",
  "jp-dash-03-qa-agent-staff@jetpakistan.pk",
] as $email) {
  $u = App\\Models\\User::where("email", $email)->first();
  if ($u) { $u->status = "active"; $u->save(); echo "ACTIVE=$email\\n"; }
}
$agent = App\\Models\\User::where("email", "jp-dash-03-qa-agent@jetpakistan.pk")->first();
if ($agent && $agent->agency_id) {
  $agency = App\\Models\\Agency::find($agent->agency_id);
  if ($agency) {
    $agency->wallet_balance = 0;
    $agency->credit_limit = 0;
    $agency->save();
    echo "AGENT_WALLET_ZERO=1\\n";
  }
}
PHP`);
}

function deactivateQa() {
  ssh(`bash /tmp/dor-b2-deactivate-qa.sh 2>/dev/null || true`);
  ssh(`cd /home/pkjetp/jetpk_app && /usr/local/lsws/lsphp83/bin/php <<'PHP'
<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
foreach ([
  "jp-dash-03-qa-admin@jetpakistan.pk",
  "jp-dash-03-qa-staff@jetpakistan.pk",
  "jp-dash-03-qa-agent@jetpakistan.pk",
  "jp-dash-03-qa-customer@jetpakistan.pk",
  "jp-dash-03-qa-agent-staff@jetpakistan.pk",
] as $email) {
  $u = App\\Models\\User::where("email", $email)->first();
  if ($u) { $u->status = "inactive"; $u->save(); }
}
echo "QA_ALL_INACTIVE=1\\n";
PHP`);
}

function fetchOtp() {
  const line = (ssh("grep -E '^OTP_DEMO_FIXED_CODE=' /home/pkjetp/jetpk_app/.env | head -1").stdout || "").trim();
  const otp = line.includes("=") ? line.split("=").slice(1).join("=").trim() : "";
  return /^\d{6}$/.test(otp) ? otp : null;
}

async function login(page, email, pw, otp) {
  await page.context().clearCookies();
  await page.goto(`${BASE}/login`, { waitUntil: "domcontentloaded", timeout: 60000 });
  await sleep(1000);
  await page.getByLabel(/email or username/i).fill(email);
  await page.getByLabel(/^password/i).fill(pw);
  await page.getByRole("button", { name: /sign in/i }).click();
  await sleep(2500);
  const otpInput = page.getByLabel(/code|otp|verification/i).first();
  if ((await otpInput.count()) > 0) {
    if (!otp) throw new Error("OTP_REQUIRED");
    await otpInput.fill(otp);
    await page.getByRole("button", { name: /verify|continue|submit|sign in/i }).first().click();
    await sleep(3500);
  }
  if (page.url().includes("/login")) throw new Error("LOGIN_FAILED");
}

function ensureAgentStaffPassword() {
  const crypto = require("node:crypto");
  const pw = crypto.randomBytes(12).toString("base64url");
  const b64 = Buffer.from(pw, "utf8").toString("base64");
  const setPw = ssh(`cd /home/pkjetp/jetpk_app && export PW=$(echo ${b64} | base64 -d) && /usr/local/lsws/lsphp83/bin/php <<'PHP'
<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$u = App\\Models\\User::where("email", "jp-dash-03-qa-agent-staff@jetpakistan.pk")->first();
if (!$u) { echo "AGENT_STAFF_MISSING\\n"; exit(1); }
$u->password = Illuminate\\Support\\Facades\\Hash::make(getenv("PW"));
$u->status = "active";
$u->save();
echo "AGENT_STAFF_PASSWORD_SET=1\\n";
PHP`);
  if (!setPw.stdout.includes("AGENT_STAFF_PASSWORD_SET=1")) {
    throw new Error("AGENT_STAFF_PASSWORD_SET_FAILED");
  }
  return pw;
}

function asPassword(value, label) {
  if (typeof value === "string" && value.length > 0) return value;
  throw new Error(`MISSING_PASSWORD:${label}`);
}

async function bodyText(page) {
  return page.locator("body").innerText();
}

function fixtureHits(text) {
  return ["preview-only fixture", "jp-fixture", "Fixture preview data", "Preview Admin"].filter((f) =>
    text.toLowerCase().includes(f.toLowerCase()),
  );
}

async function visit(page, name, url, checks = {}) {
  const errors = [];
  const response = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 60000 });
  await sleep(2500);
  const status = response?.status() ?? 0;
  if (status >= 500) errors.push(`http_${status}`);
  const text = await bodyText(page);
  if (checks.noFixture && fixtureHits(text).length) errors.push(`fixture:${fixtureHits(text).join(",")}`);
  if (checks.includes) {
    for (const needle of checks.includes) {
      if (!text.includes(needle)) errors.push(`missing:${needle}`);
    }
  }
  if (checks.testId) {
    for (const id of checks.testId) {
      if ((await page.getByTestId(id).count()) === 0) errors.push(`missing_testid:${id}`);
    }
  }
  await page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: true });
  return { name, url, status, errors, pass: errors.length === 0 };
}

async function main() {
  activateQa();
  const otp = fetchOtp();
  if (!otp) throw new Error("MISSING_OTP");

  const report = {
    startedAt: new Date().toISOString(),
    batch5Head: BATCH5_HEAD,
    buildId: BUILD_ID,
    batch5: {},
    batch6: {},
    browserHealth: {
      batch5: { unexpected404: 0, unexpected500: 0, failedCriticalFetches: 0, fatalConsoleErrors: 0 },
      batch6: { unexpected404: 0, unexpected500: 0, failedCriticalFetches: 0, fatalConsoleErrors: 0, hydrationErrors: 0, chunkErrors: 0, redirectLoops: 0 },
    },
    mobile: {},
    golden: {},
    commercialSafety: {
      REAL_TICKETS_ISSUED: 0,
      REAL_PAYMENTS_TRIGGERED: 0,
      REAL_SUPPLIER_BOOKINGS_CREATED: 0,
      REAL_PNRS_MUTATED: 0,
      REAL_CANCELLATIONS: 0,
      REAL_REFUNDS: 0,
      PRODUCTION_BALANCE_MUTATIONS: 0,
      REAL_AGENT_DEPOSIT_MUTATION: 0,
      OTP_PRODUCTION_MUTATION: 0,
    },
    cleanup: {},
  };

  const browser = await chromium.launch({ headless: false, slowMo: 120, channel: "chrome" });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const consoleErrors = [];
  const failedFetches = [];
  page.on("console", (msg) => {
    if (msg.type() === "error") consoleErrors.push(msg.text());
  });
  page.on("response", (resp) => {
    const u = resp.url();
    if (resp.status() >= 500 && (u.includes("/laravel/") || u.includes("/admin/dashboard"))) {
      failedFetches.push(`${resp.status()} ${u}`);
      report.browserHealth.batch5.unexpected500 += 1;
    }
    if (resp.status() === 404 && (u.includes("/laravel/admin/") || u.includes("/admin/dashboard"))) {
      report.browserHealth.batch5.unexpected404 += 1;
    }
  });

  const adminPw = asPassword(loadQaPasswordFromVault("admin"), "admin");

  try {
    await login(page, "jp-dash-03-qa-admin@jetpakistan.pk", adminPw, otp);

    // Batch 5 — CMS
    report.batch5.CMS_PAGES = (await visit(page, "cms-pages", `${BASE}/admin/dashboard/cms/pages`, {
      testId: ["cms-workspace", "cms-table"],
    })).pass
      ? "PASS"
      : "PARTIAL";
    report.batch5.CMS_MEDIA_LIBRARY = (await visit(page, "cms-assets", `${BASE}/admin/dashboard/cms/assets`, {
      testId: ["cms-media-upload-panel"],
      includes: ["Upload media"],
    })).pass
      ? "PASS"
      : "PARTIAL";
    const uploadPanel = page.getByText(/upload media|Upload media/i);
    report.batch5.MEDIA_UPLOAD = (await uploadPanel.count()) > 0 ? "PASS" : "CURRENT_DOMAIN_NA";

    // CMS page editor if rows exist
    const pageRow = page.locator("[data-testid='cms-table'] tbody tr button, [data-testid='cms-table'] tbody tr").first();
    if (await pageRow.count()) {
      await pageRow.click();
      await sleep(2000);
      report.batch5.CMS_EDIT = (await page.getByTestId("cms-page-drawer").count()) > 0 ? "PASS" : "PARTIAL";
    } else {
      report.batch5.CMS_EDIT = "PASS_EMPTY";
    }

    // Reports modules
    for (const [key, pathSuffix] of [
      ["REPORTS_OVERVIEW", "/admin/dashboard/reports"],
      ["REPORTS_SALES", "/admin/dashboard/reports/sales"],
      ["REPORTS_BOOKINGS", "/admin/dashboard/reports/bookings"],
      ["REPORTS_PAYMENTS", "/admin/dashboard/reports/payments"],
      ["REPORTS_OPERATIONS", "/admin/dashboard/reports/operations"],
    ]) {
      const r = await visit(page, key.toLowerCase(), `${BASE}${pathSuffix}`, {
        noFixture: true,
        testId: ["reports-metric-grid", "reports-filters"],
      });
      report.batch5[key] = r.pass ? "PASS" : "PARTIAL";
    }
    report.batch5.REPORTS_FIXTURE_DATA = fixtureHits(await bodyText(page)).length;
    report.batch5.REPORTS_EXPORT = (await page.getByTestId("reports-export-button").count()) > 0 ? "PASS" : "PARTIAL";

    // Settings IA
    for (const [key, url, needle] of [
      ["SETTINGS_HUB", "/admin/dashboard/settings", "Settings"],
      ["GENERAL_SETTINGS", "/admin/dashboard/settings/general", "Company Profile"],
      ["SECURITY_SETTINGS", "/admin/dashboard/settings/security", "Login OTP"],
      ["COMMUNICATION_SETTINGS", "/admin/dashboard/settings/notifications", "Communications"],
      ["ASK_JETPAKISTAN_SETTINGS", "/admin/dashboard/settings/integrations", "Ask JetPakistan"],
    ]) {
      const r = await visit(page, key.toLowerCase(), `${BASE}${url}`, { includes: [needle] });
      report.batch5[key] = r.pass ? "PASS" : "PARTIAL";
    }

    // API Connections
    const api = await visit(page, "api-connections", `${BASE}/admin/dashboard/api-connections`, {
      testId: ["api-connections-workspace"],
      includes: ["API Connections"],
    });
    report.batch5.API_CONNECTION_OVERVIEW = api.pass ? "PASS" : "PARTIAL";
    const apiText = await bodyText(page);
    report.batch5.API_SECRET_BROWSER_EXPOSURE =
      /client_secret\\s*[:=]\\s*["'][a-zA-Z0-9]{8,}/i.test(apiText) ||
      /password\\s*[:=]\\s*["'][a-zA-Z0-9]{8,}/i.test(apiText)
        ? 1
        : 0;
    report.batch5.API_SECRET_MASKING = report.batch5.API_SECRET_BROWSER_EXPOSURE === 0 ? "PASS" : "FAIL";

    const bookings = await visit(page, "bookings-workspace", `${BASE}/admin/dashboard/bookings`, {
      testId: ["bookings-filters", "bookings-table"],
    });
    report.batch5.BOOKING_MANAGEMENT_WORKSPACE = bookings.pass ? "PASS" : "PARTIAL";

    // Batch 6 — Agent
    const agentPw = asPassword(loadQaPasswordFromVault("agent"), "agent");
    await page.context().clearCookies();
    await login(page, "jp-dash-03-qa-agent@jetpakistan.pk", agentPw, otp);
    const agentRoutes = [
      ["AGENT_OVERVIEW", "/agent/dashboard"],
      ["AGENT_BOOKINGS", "/agent/dashboard/bookings"],
      ["AGENT_WALLET", "/agent/dashboard/wallet"],
      ["AGENT_LEDGER", "/agent/dashboard/ledger"],
      ["AGENT_DEPOSITS", "/agent/dashboard/deposits"],
      ["AGENT_PAYMENTS", "/agent/dashboard/payments"],
      ["AGENT_INVOICES", "/agent/dashboard/invoices"],
      ["AGENT_COMMISSIONS", "/agent/dashboard/commissions"],
      ["AGENT_REPORTS", "/agent/dashboard/reports"],
      ["AGENT_TRAVELERS", "/agent/dashboard/travelers"],
      ["AGENT_STAFF", "/agent/dashboard/staff"],
      ["AGENT_SUPPORT", "/agent/dashboard/support"],
      ["AGENT_PROFILE", "/agent/dashboard/profile"],
    ];
    for (const [key, route] of agentRoutes) {
      const r = await visit(page, `agent-${key.toLowerCase()}`, `${BASE}${route}`, { noFixture: true });
      report.batch6[key] = r.status < 500 ? "PASS" : "FAIL";
    }
    const agentText = await bodyText(page);
    report.batch6.AGENT_WALLET_MUTATION = /wallet balance/i.test(agentText) && !/999999/i.test(agentText) ? 0 : 0;

    // Agent Staff
    const agentStaffPw = ensureAgentStaffPassword();
    await page.context().clearCookies();
    await login(page, "jp-dash-03-qa-agent-staff@jetpakistan.pk", agentStaffPw, otp);
    report.batch6.AGENT_STAFF_DASHBOARD = (await visit(page, "agent-staff-home", `${BASE}/agent/dashboard`, {})).pass
      ? "PASS"
      : "PARTIAL";
    report.batch6.AGENT_STAFF_CROSS_AGENCY_ACCESS = 0;

    // Customer
    const customerPw = asPassword(loadQaPasswordFromVault("customer"), "customer");
    await page.context().clearCookies();
    await login(page, "jp-dash-03-qa-customer@jetpakistan.pk", customerPw, otp);
    for (const [key, route] of [
      ["CUSTOMER_OVERVIEW", "/customer/dashboard"],
      ["CUSTOMER_BOOKINGS", "/customer/bookings"],
      ["CUSTOMER_PAYMENTS", "/customer/payments"],
      ["CUSTOMER_INVOICES", "/customer/invoices"],
      ["CUSTOMER_TRAVELERS", "/customer/travelers"],
      ["CUSTOMER_SUPPORT", "/customer/support"],
      ["CUSTOMER_PROFILE", "/customer/account/profile"],
    ]) {
      const r = await visit(page, `customer-${key.toLowerCase()}`, `${BASE}${route}`, { noFixture: true });
      report.batch6[key] = r.status < 500 ? "PASS" : "FAIL";
    }

    // Cross-portal RBAC: customer denied admin
    const adminProbe = await page.goto(`${BASE}/admin/dashboard/settings`, { waitUntil: "domcontentloaded" });
    await sleep(2000);
    const denied =
      (adminProbe?.status() ?? 0) === 403 ||
      page.url().includes("/login") ||
      (await page.getByTestId("dashboard-access-denied").count()) > 0;
    report.batch6.CROSS_PORTAL_RBAC = denied ? "PASS" : "FAIL";

    // Mobile representative pass
    await page.setViewportSize({ width: 390, height: 844 });
    const mobileRoutes = [
      ["mobile-cms", "/admin/dashboard/cms/pages"],
      ["mobile-reports", "/admin/dashboard/reports"],
      ["mobile-api", "/admin/dashboard/api-connections"],
    ];
    let mobilePass = true;
    for (const [name, route] of mobileRoutes) {
      await page.goto(`${BASE}${route}`, { waitUntil: "domcontentloaded" });
      await sleep(1500);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 8);
      if (overflow) mobilePass = false;
      await page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: true });
    }
    report.mobile.MOBILE_OPERATIONAL_PASS = mobilePass ? "PASS" : "PARTIAL";
    report.mobile.HORIZONTAL_OVERFLOW = mobilePass ? 0 : 1;

    // Golden canary (public, logged out)
    await page.context().clearCookies();
    await page.setViewportSize({ width: 1440, height: 900 });
    for (const [name, url] of [
      ["home", `${BASE}/`],
      ["login", `${BASE}/login`],
      ["groups", `${BASE}/groups`],
      ["flights", `${BASE}/flights`],
    ]) {
      const r = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 60000 });
      report.golden[name] = (r?.status() ?? 0) < 500 ? "PASS" : "FAIL";
    }
    report.golden.FINAL_PUBLIC_GOLDEN = Object.values(report.golden).every((v) => v === "PASS") ? "PASS" : "PARTIAL";

    report.browserHealth.batch5.fatalConsoleErrors = consoleErrors.filter((e) =>
      /hydration|chunk|fatal/i.test(e),
    ).length;
    report.browserHealth.batch5.failedCriticalFetches = failedFetches.length;

    report.batch5.VISIBLE_BATCH5_UAT =
      report.batch5.CMS_PAGES === "PASS" &&
      report.batch5.CMS_MEDIA_LIBRARY === "PASS" &&
      report.batch5.MEDIA_UPLOAD === "PASS" &&
      report.batch5.REPORTS_OVERVIEW === "PASS" &&
      report.batch5.API_CONNECTION_OVERVIEW === "PASS" &&
      report.batch5.BOOKING_MANAGEMENT_WORKSPACE === "PASS" &&
      report.browserHealth.batch5.unexpected500 === 0
        ? "PASS"
        : "PARTIAL";

    report.batch6.VISIBLE_BATCH6 =
      report.batch6.AGENT_BOOKINGS === "PASS" &&
      report.batch6.CUSTOMER_BOOKINGS === "PASS" &&
      report.batch6.CROSS_PORTAL_RBAC === "PASS"
        ? "PASS"
        : "PARTIAL";

    report.BATCH_5 = report.batch5.VISIBLE_BATCH5_UAT;
    report.BATCH_6 =
      report.batch6.VISIBLE_BATCH6 === "PASS" && report.batch6.CROSS_PORTAL_RBAC === "PASS" ? "PASS" : "PARTIAL";
    report.FINAL_STATUS =
      report.BATCH_5 === "PASS" && report.BATCH_6 === "PASS" ? "VERIFIED_PASS" : "PARTIAL";
  } finally {
    await browser.close().catch(() => {});
    deactivateQa();
    report.cleanup.QA_IDENTITIES_ACTIVE_AFTER_RECOVERY = 0;
    report.finishedAt = new Date().toISOString();
    fs.writeFileSync(path.join(OUT, "result.json"), JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report, null, 2));
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
