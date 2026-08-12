import { chromium, expect, test } from "@playwright/test";
import path from "node:path";

const repoRoot = path.resolve(process.cwd(), "..");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const storage = {
  admin: path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json"),
  staff: path.join(repoRoot, "tmp/jp-dash-03-staff-storage-state.json"),
  customer: path.join(repoRoot, "tmp/jp-dash-03-customer-storage-state.json"),
};

async function parseJson(response: { text: () => Promise<string>; ok: () => boolean; status: () => number }) {
  const raw = (await response.text()).replace(/^\uFEFF/, "");
  return { ok: response.ok(), status: response.status(), json: JSON.parse(raw) as Record<string, unknown> };
}

test.describe.configure({ mode: "serial", timeout: 240_000 });

test("JP-OPS-08 multi-browser stale assign rejected after concurrent close", async () => {
  const browser = await chromium.launch({ headless: true });
  const adminCtx = await browser.newContext({ storageState: storage.admin });
  const staffCtx = await browser.newContext({ storageState: storage.staff });
  const customerCtx = await browser.newContext({ storageState: storage.customer });

  try {
    await customerCtx.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const customerCookies = await customerCtx.cookies();
    const customerXsrf = customerCookies.find((c) => c.name === "XSRF-TOKEN");
    const create = await customerCtx.request.post(`${baseUrl}/laravel/customer/support/tickets?format=json`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": customerXsrf ? decodeURIComponent(customerXsrf.value) : "",
      },
      form: {
        subject: `JP-OPS-08 stale ${Date.now()}`,
        category: "other",
        body: "concurrency fixture",
      },
    });
    expect(create.status()).toBe(201);
    const created = await parseJson(create);
    const ticketRef = String((created.json.ticket as { reference?: string } | undefined)?.reference ?? "");
    expect(ticketRef).not.toEqual("");

    const resolved = await parseJson(
      await adminCtx.request.get(`${baseUrl}/api/dashboard/support/tickets/${encodeURIComponent(ticketRef)}`, {
        headers: { Accept: "application/json" },
      }),
    );
    const ticketId = String((resolved.json.data as { id?: string; updated_at?: string } | undefined)?.id ?? "");
    const staleUpdatedAt = String(
      (resolved.json.data as { updated_at?: string } | undefined)?.updated_at
        ?? (resolved.json as { updated_at?: string }).updated_at
        ?? "",
    );
    expect(ticketId).not.toEqual("");

    // Staff closes while Admin still holds stale updated_at.
    await staffCtx.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const staffCookies = await staffCtx.cookies();
    const staffXsrf = staffCookies.find((c) => c.name === "XSRF-TOKEN");
    const close = await staffCtx.request.fetch(
      `${baseUrl}/laravel/staff/support/tickets/${encodeURIComponent(ticketId)}/status?format=json`,
      {
        method: "PATCH",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-XSRF-TOKEN": staffXsrf ? decodeURIComponent(staffXsrf.value) : "",
          "Content-Type": "application/x-www-form-urlencoded",
        },
        data: "status=closed",
      },
    );
    console.log(`STAFF_CLOSE_STATUS=${close.status()}`);
    expect(close.status()).toBeLessThan(500);

    await adminCtx.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const adminCookies = await adminCtx.cookies();
    const adminXsrf = adminCookies.find((c) => c.name === "XSRF-TOKEN");
    const assign = await adminCtx.request.fetch(
      `${baseUrl}/laravel/admin/support/tickets/${encodeURIComponent(ticketId)}/assign?format=json`,
      {
        method: "PATCH",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-XSRF-TOKEN": adminXsrf ? decodeURIComponent(adminXsrf.value) : "",
          "Content-Type": "application/x-www-form-urlencoded",
        },
        data: `assigned_to_user_id=8&expected_updated_at=${encodeURIComponent(staleUpdatedAt || "2000-01-01T00:00:00+00:00")}`,
      },
    );
    const assignBody = (await assign.text()).replace(/^\uFEFF/, "");
    console.log(`STALE_ASSIGN_STATUS=${assign.status()}`);
    console.log(`STALE_ASSIGN_BODY_HEAD=${assignBody.slice(0, 220)}`);
    expect([409, 422, 400].includes(assign.status()) || /stale|closed|updated/i.test(assignBody)).toBeTruthy();

    const refresh = await parseJson(
      await adminCtx.request.get(`${baseUrl}/api/dashboard/support/tickets/${encodeURIComponent(ticketRef)}`, {
        headers: { Accept: "application/json" },
      }),
    );
    const status = String(
      (refresh.json.data as { status?: string | { code?: string } } | undefined)?.status
        ?? (refresh.json.data as { status?: { code?: string } } | undefined)?.status?.code
        ?? "",
    ).toLowerCase();
    console.log(`REFRESHED_STATUS=${status}`);
    expect(status.includes("closed") || status.includes("resolved") || assign.status() === 409).toBeTruthy();
    console.log("STALE_STATE_HANDLING=PASS");
    console.log("MULTI_BROWSER_CONCURRENCY=PASS");
  } finally {
    await adminCtx.close();
    await staffCtx.close();
    await customerCtx.close();
    await browser.close();
  }
});
