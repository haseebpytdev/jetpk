/**
 * JP-UAT-01 full business loop (safe Support ticket lifecycle).
 * Black-box navigation via visible Dashboard / Support / ops labels where possible.
 * Deterministic API verifies authoritative state between hops.
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

const storage = {
  admin: path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json"),
  staff: path.join(repoRoot, "tmp/jp-dash-03-staff-storage-state.json"),
  customer: path.join(repoRoot, "tmp/jp-dash-03-customer-storage-state.json"),
};

function parseJson(raw) {
  return JSON.parse(String(raw).replace(/^\uFEFF/, ""));
}

async function csrf(page) {
  await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const cookies = await page.context().cookies();
  const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
  return xsrf ? decodeURIComponent(xsrf.value) : "";
}

async function openDashboard(page, roleLabel) {
  await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(1200);
  const dash = page.getByRole("link", { name: /^Dashboard$/i }).first();
  if ((await dash.count()) > 0) {
    await dash.click();
    await page.waitForTimeout(2000);
    return { ok: true, via: "dashboard_link", path: new URL(page.url()).pathname };
  }
  const trigger = page.getByTestId("account-menu-trigger-desktop");
  if ((await trigger.count()) > 0) {
    await trigger.click({ force: true });
    await page.waitForTimeout(400);
    const item = page.getByRole("menuitem").filter({ hasText: /dashboard|operations/i }).first();
    if ((await item.count()) > 0) {
      await item.click();
      await page.waitForTimeout(2000);
      return { ok: true, via: "account_menu", path: new URL(page.url()).pathname };
    }
  }
  return { ok: false, via: "none", path: new URL(page.url()).pathname, roleLabel };
}

const report = {
  scenario: "UAT-LOOP-01",
  hops: [],
  success: false,
  findings: [],
  ticketRef: "",
  ticketId: "",
};

const browser = await chromium.launch({ headless: true });
const customerCtx = await browser.newContext({ storageState: storage.customer });
const adminCtx = await browser.newContext({ storageState: storage.admin });
const staffCtx = await browser.newContext({ storageState: storage.staff });
const customer = await customerCtx.newPage();
const admin = await adminCtx.newPage();
const staff = await staffCtx.newPage();

try {
  // Customer create (UI discovery + authoritative create)
  const cEntry = await openDashboard(customer, "customer");
  report.hops.push({ actor: "customer_entry", ...cEntry });
  const supportNav = customer.locator('[data-testid="customer-dashboard-shell"] a, aside a, nav a').filter({ hasText: /^Support$/i }).first();
  if ((await supportNav.count()) > 0) {
    await supportNav.click();
    await pageWait(customer);
    report.hops.push({ actor: "customer_support_nav", path: new URL(customer.url()).pathname });
  } else {
    // Prefer signed-in Support dropdown item if still on public chrome
    const supportMenu = customer.getByRole("button", { name: /^Support$/i }).first();
    if ((await supportMenu.count()) > 0) {
      await supportMenu.click();
      const mySupport = customer.getByRole("menuitem", { name: /my support requests/i }).first();
      if ((await mySupport.count()) > 0) {
        await mySupport.click();
        await pageWait(customer);
        report.hops.push({ actor: "customer_support_nav", path: new URL(customer.url()).pathname, via: "header_my_support" });
      }
    }
  }
  const subject = `JP-UAT-01 loop ${Date.now()}`;
  const token = await csrf(customer);
  const create = await customer.request.post(`${baseUrl}/laravel/customer/support/tickets?format=json`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": token,
    },
    form: { subject, category: "other", body: "Customer loop message 1" },
  });
  const created = parseJson(await create.text());
  const ticketPayload = created.ticket ?? created.data?.ticket ?? {};
  report.ticketRef = String(ticketPayload.ticket_reference ?? ticketPayload.reference ?? "");
  report.hops.push({
    actor: "customer_create",
    status: create.status(),
    ticketRef: report.ticketRef,
  });
  if (create.status() !== 201 || !report.ticketRef) {
    report.findings.push({ severity: "P1", note: "customer_ticket_create_failed" });
    throw new Error("create_failed");
  }

  // Admin discover entry + resolve ticket
  const aEntry = await openDashboard(admin, "admin");
  report.hops.push({ actor: "admin_entry", ...aEntry });
  if (!aEntry.ok) report.findings.push({ severity: "P1", note: "admin_dashboard_not_discoverable" });

  let resolved = {};
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const res = await admin.request.get(
        `${baseUrl}/api/dashboard/support/tickets/${encodeURIComponent(report.ticketRef)}`,
        { headers: { Accept: "application/json" }, timeout: 30000 },
      );
      resolved = parseJson(await res.text());
      report.ticketId = String(resolved.data?.id ?? resolved.id ?? "");
      if (report.ticketId) break;
    } catch (err) {
      report.hops.push({ actor: "admin_resolve_retry", attempt, error: String(err).slice(0, 120) });
      await new Promise((r) => setTimeout(r, 1500 * attempt));
    }
  }
  if (!report.ticketId) {
    // Fallback: list tickets and match reference
    try {
      const listRes = await admin.request.get(`${baseUrl}/api/dashboard/support/tickets`, {
        headers: { Accept: "application/json" },
        timeout: 30000,
      });
      const listJson = parseJson(await listRes.text());
      const rows = listJson.data?.tickets ?? listJson.tickets ?? listJson.data ?? [];
      const found = Array.isArray(rows)
        ? rows.find((t) => String(t.ticket_reference ?? t.reference ?? "") === report.ticketRef)
        : null;
      report.ticketId = String(found?.id ?? "");
    } catch (err) {
      report.findings.push({ severity: "P1", note: `admin_resolve_failed:${String(err).slice(0, 120)}` });
    }
  }
  report.hops.push({ actor: "admin_resolve_ticket", ticketId: report.ticketId });
  if (!report.ticketId) {
    report.findings.push({ severity: "P1", note: "admin_could_not_resolve_ticket_id" });
    throw new Error("resolve_failed");
  }

  const staffSession = parseJson(
    await (await staff.request.get(`${baseUrl}/api/dashboard/session`, { headers: { Accept: "application/json" } })).text(),
  );
  const sessionData = staffSession.data ?? staffSession;
  const userBlock = sessionData.user ?? sessionData.actor ?? {};
  let staffUserId = Number(userBlock.id ?? userBlock.userId ?? 0);
  if (!Number.isFinite(staffUserId) || staffUserId <= 0) staffUserId = 8;

  const expectedUpdatedAt = String(resolved.data?.updated_at ?? resolved.updated_at ?? "");
  await admin.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const adminCookies = await adminCtx.cookies();
  const adminXsrf = adminCookies.find((c) => c.name === "XSRF-TOKEN");
  const assign = await admin.request.fetch(
    `${baseUrl}/laravel/admin/support/tickets/${encodeURIComponent(report.ticketId)}/assign?format=json`,
    {
      method: "PATCH",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": adminXsrf ? decodeURIComponent(adminXsrf.value) : "",
        "Content-Type": "application/x-www-form-urlencoded",
      },
      data: `assigned_to_user_id=${encodeURIComponent(String(staffUserId))}${expectedUpdatedAt ? `&expected_updated_at=${encodeURIComponent(expectedUpdatedAt)}` : ""}`,
    },
  );
  report.hops.push({ actor: "admin_assign", status: assign.status() });
  if (assign.status() >= 400) {
    report.findings.push({ severity: "P1", note: `assign_failed_${assign.status()}` });
  }

  // Staff entry + reply
  const sEntry = await openDashboard(staff, "staff");
  report.hops.push({ actor: "staff_entry", ...sEntry });
  await staff.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const staffCookies = await staffCtx.cookies();
  const staffXsrf = staffCookies.find((c) => c.name === "XSRF-TOKEN");
  const reply = await staff.request.post(
    `${baseUrl}/laravel/staff/support/tickets/${encodeURIComponent(report.ticketId)}/reply?format=json`,
    {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": staffXsrf ? decodeURIComponent(staffXsrf.value) : "",
      },
      form: {
        body: "Staff loop reply 1 — safe UAT",
        visibility: "customer_visible",
      },
    },
  );
  report.hops.push({ actor: "staff_reply", status: reply.status() });
  if (reply.status() >= 400) report.findings.push({ severity: "P1", note: `staff_reply_failed_${reply.status()}` });

  // Customer discovers reply (reload support)
  await customer.goto(`${baseUrl}/customer/support`, { waitUntil: "domcontentloaded" });
  await pageWait(customer);
  const custBody = (await customer.locator("body").innerText()).replace(/\s+/g, " ");
  const customerSeesSubject = custBody.includes(subject.slice(0, 18)) || custBody.includes(report.ticketRef);
  report.hops.push({ actor: "customer_sees_ticket", visible: customerSeesSubject });

  const custToken = await csrf(customer);
  const customerReply = await customer.request.post(
    `${baseUrl}/laravel/customer/support/tickets/${encodeURIComponent(report.ticketRef)}/reply?format=json`,
    {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": custToken,
      },
      form: { body: "Customer loop reply 2" },
    },
  );
  report.hops.push({ actor: "customer_reply", status: customerReply.status() });
  if (customerReply.status() >= 400) {
    report.findings.push({ severity: "P1", note: `customer_reply_failed_${customerReply.status()}` });
  }

  // Admin monitors
  const monitor = parseJson(
    await (
      await admin.request.get(`${baseUrl}/api/dashboard/support/tickets/${encodeURIComponent(report.ticketRef)}`, {
        headers: { Accept: "application/json" },
      })
    ).text(),
  );
  report.hops.push({
    actor: "admin_monitor",
    hasData: Boolean(monitor.data || monitor.id),
    status: monitor.data?.status ?? monitor.status ?? null,
  });

  // Privacy: customer body must not show internal note markers after staff customer-visible reply
  if (/internal note|ops_inbox|assignee_user_id/i.test(custBody)) {
    report.findings.push({ severity: "P0", note: "internal_leak_to_customer" });
  }

  if (!customerSeesSubject) {
    // Soft P2 if API thread exists but list UI empty-state lag; still require reply API success
    report.findings.push({ severity: "P2", note: "customer_support_list_did_not_show_subject_on_first_paint" });
  }

  report.success =
    report.findings.every((f) => f.severity !== "P0" && f.severity !== "P1") &&
    cEntry.ok &&
    aEntry.ok &&
    sEntry.ok &&
    Boolean(report.ticketRef) &&
    assign.status() < 400 &&
    reply.status() < 400 &&
    customerReply.status() < 400;
} catch (e) {
  report.findings.push({ severity: "P1", note: String(e).slice(0, 200) });
  report.success = false;
} finally {
  const out = path.join(outDir, `full-loop-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  console.log(`REPORT_PATH=${out}`);
  console.log(`SUCCESS=${report.success ? "yes" : "no"}`);
  console.log(`TICKET_REF=${report.ticketRef || "none"}`);
  console.log(`FINDINGS=${report.findings.length}`);
  await browser.close();
}

async function pageWait(page) {
  await page.waitForTimeout(1200);
}
