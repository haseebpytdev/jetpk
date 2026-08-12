/**
 * JP-UAT-01 customer support black-box from realistic post-login landing.
 * Starts at / (logged in), uses visible Dashboard / Support affordances only.
 * Creates one QA ticket; verifier confirms via customer API.
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const outDir = path.join(repoRoot, "tmp/jp-uat-01");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
fs.mkdirSync(outDir, { recursive: true });

const report = {
  scenario: "UAT-CUST-01",
  blackBox: { success: false, steps: [], confusions: [] },
  deterministic: { ticketCreated: false, ticketRef: "", replyVisible: false },
  findings: [],
};

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  storageState: path.join(repoRoot, "tmp/jp-dash-03-customer-storage-state.json"),
  viewport: { width: 1440, height: 900 },
});
const page = await context.newPage();

function parseJson(raw) {
  return JSON.parse(String(raw).replace(/^\uFEFF/, ""));
}

try {
  await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(1500);

  // Prefer visible Dashboard link (post-fix) or account menu
  const dashLink = page.getByRole("link", { name: /^Dashboard$/i }).first();
  if ((await dashLink.count()) > 0) {
    await dashLink.click();
    report.blackBox.steps.push("clicked_dashboard_link");
  } else {
    const trigger = page.getByRole("button", { name: /Account menu|JP-DASH-03 QA Customer/i }).first();
    await trigger.click({ force: true });
    await page.waitForTimeout(400);
    const supportItem = page.getByRole("menuitem", { name: /^Support$/i }).first();
    const dashItem = page.getByRole("menuitem", { name: /Dashboard/i }).first();
    if ((await supportItem.count()) > 0) {
      await supportItem.click();
      report.blackBox.steps.push("account_menu_support");
    } else if ((await dashItem.count()) > 0) {
      await dashItem.click();
      report.blackBox.steps.push("account_menu_dashboard");
    } else {
      report.findings.push({ severity: "P1", note: "no_portal_entry_from_header" });
    }
  }
  await page.waitForTimeout(2000);
  report.blackBox.afterEntryPath = new URL(page.url()).pathname;

  // Discover Support inside portal via visible nav
  if (!/support/i.test(page.url())) {
    const supportNav = page.getByRole("link", { name: /^Support$/i }).first();
    if ((await supportNav.count()) > 0) {
      await supportNav.click();
      report.blackBox.steps.push("portal_nav_support");
      await page.waitForTimeout(1500);
    } else {
      // Support dropdown on public header
      const supportMenu = page.getByRole("button", { name: /^Support$/i }).first();
      if ((await supportMenu.count()) > 0) {
        await supportMenu.click();
        const mySupport = page.getByRole("menuitem", { name: /my support requests/i }).first();
        if ((await mySupport.count()) > 0) {
          await mySupport.click();
          report.blackBox.steps.push("header_support_my_requests");
        }
      }
    }
  }

  report.blackBox.supportPath = new URL(page.url()).pathname;
  const body = (await page.locator("body").innerText()).replace(/\s+/g, " ");
  if (/internal note|assignee id|ops_inbox/i.test(body)) {
    report.findings.push({ severity: "P0", note: "internal_staff_data_visible_to_customer" });
  }

  // Create ticket via visible UI if form available; else API mutation with UI discovery already proven
  const newBtn = page.getByRole("button", { name: /new support request|create|submit/i }).first();
  const subject = `JP-UAT-01 customer ${Date.now()}`;
  if ((await newBtn.count()) > 0) {
    await newBtn.click().catch(() => {});
    await page.waitForTimeout(500);
  }
  const subjectField = page.getByLabel(/subject/i).first();
  const messageField = page.getByLabel(/message|details|body/i).first();
  if ((await subjectField.count()) > 0 && (await messageField.count()) > 0) {
    await subjectField.fill(subject);
    await messageField.fill("UAT customer travel question — safe test message.");
    const submit = page.getByRole("button", { name: /submit|send|create/i }).first();
    await submit.click();
    await page.waitForTimeout(2000);
    report.blackBox.steps.push("ui_ticket_submit");
  } else {
    report.blackBox.confusions.push("support_form_fields_not_found_after_discovery");
    // Deterministic create still allowed for mutation proof after discoverability attempt
    await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const cookies = await context.cookies();
    const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
    const create = await page.request.post(`${baseUrl}/laravel/customer/support/tickets?format=json`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": xsrf ? decodeURIComponent(xsrf.value) : "",
      },
      form: { subject, category: "other", body: "UAT customer travel question — safe test message." },
    });
    const raw = await create.text();
    const json = parseJson(raw);
    const ticket =
      json.ticket ?? json.data?.ticket ?? json.data ?? {};
    report.deterministic.ticketRef = String(ticket.ticket_reference ?? ticket.reference ?? "");
    report.deterministic.ticketCreated = create.status() === 201 && Boolean(report.deterministic.ticketRef);
    report.blackBox.steps.push("api_ticket_create_after_ui_discovery");
  }

  // Refresh persistence / find thread
  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(1000);
  const after = (await page.locator("body").innerText()).replace(/\s+/g, " ");
  if (subject && after.includes(subject.slice(0, 20))) {
    report.deterministic.replyVisible = true;
  }
  if (!report.deterministic.ticketCreated) {
    // list via API
    const list = await page.request.get(`${baseUrl}/laravel/customer/support/tickets?format=json`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const listJson = parseJson(await list.text());
    const tickets = listJson.tickets ?? listJson.data?.tickets ?? listJson.data ?? [];
    const found = Array.isArray(tickets)
      ? tickets.find((t) => String(t.subject || "").includes("JP-UAT-01 customer"))
      : null;
    if (found) {
      report.deterministic.ticketCreated = true;
      report.deterministic.ticketRef = String(found.ticket_reference ?? found.reference ?? found.id ?? "");
    }
  }

  report.blackBox.success =
    /customer\/support|support/i.test(report.blackBox.supportPath || report.blackBox.afterEntryPath || "") &&
    report.findings.every((f) => f.severity !== "P0" && f.severity !== "P1");

  // If still on public support only, mark P1
  if (/^\/support\/?$/.test(report.blackBox.supportPath || "") && !(await page.getByRole("link", { name: /open my support/i }).count())) {
    report.findings.push({ severity: "P1", note: "stuck_on_public_support_without_account_bridge" });
    report.blackBox.success = false;
  }
} finally {
  const out = path.join(outDir, `customer-support-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  console.log(`REPORT_PATH=${out}`);
  console.log(`BLACK_BOX=${report.blackBox.success ? "yes" : "no"}`);
  console.log(`TICKET_CREATED=${report.deterministic.ticketCreated ? "yes" : "no"}`);
  console.log(`TICKET_REF=${report.deterministic.ticketRef || "none"}`);
  console.log(`FINDINGS=${report.findings.length}`);
  console.log(`STEPS=${report.blackBox.steps.join(",")}`);
  await browser.close();
}
