/**
 * Local visual UAT capture for Group payment hierarchy (mocked API).
 * Run from repo root with a built frontend smoke server available, or let this
 * script start one via PLAYWRIGHT_PORT (default 3017).
 *
 *   node docs/evidence/jp-final-visual-uat/capture-group-payment-local.mjs
 */
import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { spawn } from "child_process";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const frontendRoot = path.join(repoRoot, "frontend");
const outDir = path.join(__dirname, "groups");
const buildIdPath = path.join(frontendRoot, ".next", "BUILD_ID");
const releaseSha = process.env.RELEASE_SHA || "local-uncommitted";
const buildId = fs.existsSync(buildIdPath) ? fs.readFileSync(buildIdPath, "utf8").trim() : "unknown";

fs.mkdirSync(outDir, { recursive: true });

const widths = [320, 360, 375, 390, 412, 768, 1024, 1440];
const port = process.env.PLAYWRIGHT_PORT || "3017";
const baseURL = `http://127.0.0.1:${port}`;

const paymentPayload = {
  success: true,
  reference: "GRP-VISUAL-QA",
  status: "payment_pending",
  status_label: "Payment pending",
  payment_status: "awaiting_payment",
  payment_status_label: "Awaiting payment",
  status_message: "Awaiting manual payment submission",
  seat_count: 3,
  total_amount: 297000,
  total_formatted: "297,000",
  currency: "PKR",
  expires_at: new Date(Date.now() + 18 * 60 * 1000).toISOString(),
  server_time: new Date().toISOString(),
  hold_minutes: 25,
  is_expired: false,
  is_releasable: true,
  is_payment_window_open: true,
  contact: { name: "QA Traveler", email: "qa@example.com", phone: "+923001111111" },
  passengers: [],
  inventory: {
    id: 1,
    public_id: "ALH-VISUAL-1",
    title: "Air Arabia Group — Sialkot to Sharjah",
    sector_code: "SKT-SHJ",
    route_line: "Sialkot → Sharjah",
    departure_date_short: "15 Aug 2026",
    airline_name: "Air Arabia",
    price_formatted: "99,000",
    currency: "PKR",
    available_seats: 12,
    seat_label: "12 seats left",
  },
  checkout_summary: {},
  progress: [
    { key: "package", label: "Package Selected", state: "completed" },
    { key: "passengers", label: "Passenger Details", state: "completed" },
    { key: "review", label: "Review", state: "completed" },
    { key: "payment", label: "Manual Payment", state: "current" },
    { key: "confirmation", label: "Confirmation", state: "upcoming" },
  ],
  payment_methods: [
    { value: "bank_transfer", title: "Bank transfer", hint: "Transfer the total amount to the JetPakistan account and keep your receipt." },
    { value: "office", title: "Pay at office / consultant", hint: "Pay in person at a JetPakistan office or with your assigned consultant." },
    { value: "cash", title: "Cash deposit", hint: "Deposit cash at the designated collection point and retain the deposit slip." },
  ],
  payment_proof_supported: true,
  payment_reference_required: true,
  instructions: ["Include booking reference GRP-VISUAL-QA in the payment note.", "Upload a clear photo or PDF of the receipt if available."],
  support: { support_path: "/support", phone: null, email: "ota@jetpakistan.pk", whatsapp: null },
};

function startServer() {
  return new Promise((resolve, reject) => {
    const child = spawn("node", ["scripts/playwright-server.mjs"], {
      cwd: frontendRoot,
      env: {
        ...process.env,
        PLAYWRIGHT_PORT: port,
        NODE_ENV: "production",
        NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
        OTA_ALLOW_SESSION_FIXTURE: "true",
        OTA_ALLOW_CONTENT_FIXTURE: "true",
      },
      stdio: ["ignore", "pipe", "pipe"],
    });
    let ready = false;
    const onData = (buf) => {
      const text = buf.toString();
      if (!ready && /Ready/i.test(text)) {
        ready = true;
        resolve(child);
      }
    };
    child.stdout.on("data", onData);
    child.stderr.on("data", onData);
    child.on("exit", (code) => {
      if (!ready) reject(new Error(`server exited early: ${code}`));
    });
    setTimeout(() => {
      if (!ready) reject(new Error("server start timeout"));
    }, 120_000);
  });
}

async function metrics(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const vw = window.innerWidth;
    const scrollW = Math.max(doc.scrollWidth, body?.scrollWidth || 0);
    return {
      overflowX: scrollW - vw > 2 ? Math.round(scrollW - vw) : 0,
      hasCompletePayment: !!document.querySelector("h1"),
      h1: document.querySelector("h1")?.textContent?.trim() || "",
      methodCards: document.querySelectorAll("[data-testid^='group-payment-method-']").length,
      submitVisible: !!document.querySelector("[data-testid='group-payment-submit']"),
    };
  });
}

const manifest = [];
let server;

try {
  server = await startServer();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  await page.route("**/laravel/groups/booking/**/payment**", async (route) => {
    if (route.request().method() === "GET") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(paymentPayload),
      });
      return;
    }
    await route.fulfill({ status: 422, contentType: "application/json", body: JSON.stringify({ message: "not submitted in visual capture" }) });
  });

  for (const width of widths) {
    await page.setViewportSize({ width, height: width < 768 ? 844 : 900 });
    await page.goto(`${baseURL}/groups/booking/GRP-VISUAL-QA/payment`, { waitUntil: "domcontentloaded", timeout: 60_000 });
    await page.getByRole("heading", { name: "Complete payment" }).waitFor({ timeout: 30000 });

    const file = `group-payment-w${width}.png`;
    const abs = path.join(outDir, file);
    await page.screenshot({ path: abs, fullPage: true });
    const m = await metrics(page);

    // validation state at 390
    if (width === 390) {
      await page.getByTestId("group-payment-submit").click();
      await page.getByTestId("form-error-summary").waitFor({ timeout: 5000 });
      const vFile = "group-payment-validation-w390.png";
      await page.screenshot({ path: path.join(outDir, vFile), fullPage: true });
      manifest.push({
        FILE: vFile,
        URL_ROUTE: "/groups/booking/GRP-VISUAL-QA/payment",
        VIEWPORT: 390,
        ROLE: "customer",
        STATE: "client_validation_error",
        BUILD_ID: buildId,
        RELEASE_SHA: releaseSha,
        NOTES: "Inline JP validation; no native required tooltip reliance",
        SANITIZED: "YES",
        METRICS: m,
      });
      await page.reload({ waitUntil: "networkidle" });
      await page.getByRole("heading", { name: "Complete payment" }).waitFor();
    }

    if (width === 390) {
      await page.getByTestId("group-payment-method-office").click();
      const sFile = "group-payment-method-office-w390.png";
      await page.screenshot({ path: path.join(outDir, sFile), fullPage: true });
      manifest.push({
        FILE: sFile,
        URL_ROUTE: "/groups/booking/GRP-VISUAL-QA/payment",
        VIEWPORT: 390,
        ROLE: "customer",
        STATE: "payment_method_office_selected",
        BUILD_ID: buildId,
        RELEASE_SHA: releaseSha,
        NOTES: "Selectable method cards",
        SANITIZED: "YES",
        METRICS: m,
      });
    }

    manifest.push({
      FILE: file,
      URL_ROUTE: "/groups/booking/GRP-VISUAL-QA/payment",
      VIEWPORT: width,
      ROLE: "customer",
      STATE: "payment_form_default",
      BUILD_ID: buildId,
      RELEASE_SHA: releaseSha,
      NOTES: `overflowX=${m.overflowX}; methods=${m.methodCards}; h1=${m.h1}`,
      SANITIZED: "YES",
      METRICS: m,
    });
  }

  await browser.close();
} finally {
  if (server) server.kill("SIGTERM");
}

const manifestPath = path.join(__dirname, "manifest.md");
const lines = [
  "# JetPakistan Final Visual UAT — Manifest",
  "",
  `RELEASE_SHA: ${releaseSha}`,
  `BUILD_ID: ${buildId}`,
  `CAPTURED_AT: ${new Date().toISOString()}`,
  `SOURCE: local mocked Group payment (synthetic QA data)`,
  `SANITIZED: YES`,
  "",
  "| FILE | URL/ROUTE | VIEWPORT | ROLE | STATE | NOTES |",
  "| --- | --- | --- | --- | --- | --- |",
];
for (const row of manifest) {
  lines.push(`| ${row.FILE} | ${row.URL_ROUTE} | ${row.VIEWPORT} | ${row.ROLE} | ${row.STATE} | ${row.NOTES} |`);
}
fs.writeFileSync(manifestPath, lines.join("\n") + "\n");
fs.writeFileSync(path.join(__dirname, "manifest.json"), JSON.stringify({ releaseSha, buildId, rows: manifest }, null, 2));
console.log(JSON.stringify({ outDir, count: manifest.length, buildId, releaseSha }, null, 2));
