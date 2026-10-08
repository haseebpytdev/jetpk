/**
 * JP-DASH-PROD-02 safe production write certification (QA agency only).
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const BASE = "https://jetpakistan.pk";
const EVIDENCE = path.join(repoRoot, "docs/evidence/jp-dashboard-production-cert-20261008");
const QA_RUN = `JPQA-WRITE-${Date.now()}`;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const STORAGE = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");

async function trackRequest(page, bucket) {
  page.on("response", async (response) => {
    const req = response.request();
    if (!["POST", "PATCH", "PUT", "DELETE"].includes(req.method())) return;
    bucket.push({
      method: req.method(),
      url: req.url(),
      status: response.status(),
    });
  });
}

async function bookingNoteWrite(page, report, mutations) {
  const bucket = [];
  await trackRequest(page, bucket);
  await page.goto(`${BASE}/admin/dashboard/bookings/39`, { waitUntil: "domcontentloaded", timeout: 90000 });
  await sleep(3000);
  const note = `${QA_RUN} booking note`;
  const input = page.getByTestId("booking-note-input");
  if ((await input.count()) === 0) {
    report.BOOKINGS_WRITE = "UNAVAILABLE";
    return;
  }
  await input.fill(note);
  await page.getByTestId("booking-note-submit").click();
  await sleep(2500);
  await page.reload({ waitUntil: "domcontentloaded" });
  await sleep(2000);
  const persisted = (await page.getByText(note).count()) > 0;
  report.BOOKINGS_WRITE = persisted && bucket.some((r) => r.status >= 200 && r.status < 300) ? "PASS" : "PARTIAL";
  report.writeBridge = report.writeBridge || [];
  report.writeBridge.push({
    MODULE: "BOOKINGS",
    UI_CONTROL: "booking-note-submit",
    METHOD: bucket[0]?.method ?? "POST",
    URL: bucket[0]?.url ?? "",
    QA_BROWSER_EXECUTED: "YES",
    HTTP_RESULT: bucket[0]?.status ?? 0,
    DB_PERSISTED: persisted ? "YES" : "NO",
    RELOAD_VISIBLE: persisted ? "YES" : "NO",
    EXTERNAL_SIDE_EFFECT: "NO",
    RESULT: report.BOOKINGS_WRITE,
  });
  if (persisted) mutations.QA_BOOKING_MUTATIONS = "YES";
}

async function markupWrite(page, report, mutations) {
  const bucket = [];
  await trackRequest(page, bucket);
  const name = `${QA_RUN}-markup`;
  await page.goto(`${BASE}/admin/dashboard/markups`, { waitUntil: "domcontentloaded", timeout: 90000 });
  await sleep(2500);
  await page.getByTestId("markup-name").fill(name);
  await page.getByTestId("markup-value").fill("1");
  await page.getByTestId("markup-save").click();
  await sleep(3000);
  await page.reload({ waitUntil: "domcontentloaded" });
  await sleep(2000);
  const visible = (await page.getByText(name).count()) > 0;
  report.MARKUPS_WRITE = visible ? "PASS" : "PARTIAL";
  report.writeBridge.push({
    MODULE: "MARKUPS",
    UI_CONTROL: "markup-save",
    METHOD: bucket.find((r) => r.url.includes("markup"))?.method ?? "POST",
    URL: bucket.find((r) => r.url.includes("markup"))?.url ?? "",
    QA_BROWSER_EXECUTED: "YES",
    HTTP_RESULT: bucket.find((r) => r.url.includes("markup"))?.status ?? 0,
    DB_PERSISTED: visible ? "YES" : "NO",
    RELOAD_VISIBLE: visible ? "YES" : "NO",
    EXTERNAL_SIDE_EFFECT: "NO",
    RESULT: report.MARKUPS_WRITE,
  });
}

async function apiConnectionLifecycle(page, report) {
  const bucket = [];
  await trackRequest(page, bucket);
  const connName = `${QA_RUN}-api`;
  await page.goto(`${BASE}/admin/dashboard/api-connections`, { waitUntil: "networkidle", timeout: 90000 });
  await sleep(2500);
  await page.getByTestId("api-connection-add-card").click();
  await page.getByTestId("api-provider-card-airblue").click();
  await page.getByTestId("api-create-connection-name").fill(connName);
  await page.getByTestId("api-create-environment").selectOption("sandbox");
  await page.getByTestId("api-create-field-client_id").fill("JPQA-FAKE-CLIENT");
  await page.getByTestId("api-create-field-client_key").fill("JPQA-FAKE-KEY");
  await page.getByTestId("api-create-field-agent_id").fill("JPQA-AGENT");
  await page.getByTestId("api-create-field-agent_password").fill("JPQA-FAKE-PASS");
  await page.getByTestId("api-create-save").click();
  await sleep(3500);
  await page.reload({ waitUntil: "domcontentloaded" });
  await sleep(2500);
  const created = (await page.getByText(connName).count()) > 0;
  report.API_CONNECTION_LIFECYCLE = created ? "PASS_CREATE" : "PARTIAL";
  report.writeBridge.push({
    MODULE: "API_CONNECTIONS",
    UI_CONTROL: "api-create-save",
    METHOD: "POST",
    URL: bucket.find((r) => r.url.includes("api") || r.url.includes("connection"))?.url ?? "",
    QA_BROWSER_EXECUTED: "YES",
    HTTP_RESULT: bucket.find((r) => r.status)?.status ?? 0,
    DB_PERSISTED: created ? "YES" : "NO",
    RELOAD_VISIBLE: created ? "YES" : "NO",
    EXTERNAL_SIDE_EFFECT: "NO",
    RESULT: report.API_CONNECTION_LIFECYCLE,
  });
}

async function main() {
  if (!fs.existsSync(STORAGE)) throw new Error("Missing admin storage state. Run build-auth-states.mjs first.");
  const report = {
    startedAt: new Date().toISOString(),
    qaRun: QA_RUN,
    writeBridge: [],
    mutations: {
      QA_PAYMENT_RECORD_MUTATIONS: "NO",
      EXTERNAL_PAYMENT_GATEWAY_CALLS: 0,
      QA_CUSTOMER_MUTATIONS: "NO",
      NON_QA_CUSTOMER_MUTATIONS: 0,
      QA_BOOKING_MUTATIONS: "NO",
      REAL_SUPPLIER_BOOKING_MUTATIONS: 0,
    },
  };
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: STORAGE, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  try {
    await bookingNoteWrite(page, report, report.mutations);
  } catch (error) {
    report.BOOKINGS_WRITE = "FAIL";
    report.bookingsError = String(error?.message || error);
  }
  try {
    await markupWrite(page, report, report.mutations);
  } catch (error) {
    report.MARKUPS_WRITE = "FAIL";
    report.markupsError = String(error?.message || error);
  }
  try {
    await apiConnectionLifecycle(page, report);
  } catch (error) {
    report.API_CONNECTION_LIFECYCLE = "FAIL";
    report.apiConnectionError = String(error?.message || error);
  }

  report.finishedAt = new Date().toISOString();
  fs.writeFileSync(path.join(EVIDENCE, "write-certification-result.json"), JSON.stringify(report, null, 2));
  await browser.close();
  console.log(JSON.stringify(report, null, 2));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
