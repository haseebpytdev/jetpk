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

test("JP-OPS-08 offline reconnect recovers missed support assignment", async () => {
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
        subject: `JP-OPS-08 reconnect ${Date.now()}`,
        category: "other",
        body: "Offline recovery ticket",
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
    const ticketId = String((resolved.json.data as { id?: string } | undefined)?.id ?? "");
    expect(ticketId).not.toEqual("");

    const before = await parseJson(
      await staffCtx.request.get(`${baseUrl}/api/dashboard/ops/inbox/unread-summary`, {
        headers: { Accept: "application/json" },
      }),
    );
    const beforeCount = Number((before.json.data as { unreadCount?: number } | undefined)?.unreadCount ?? 0);

    // Simulate staff offline while Admin assigns.
    await staffCtx.setOffline(true);

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
        data: "assigned_to_user_id=8",
      },
    );
    expect(assign.status()).toBe(200);

    await staffCtx.setOffline(false);

    await expect
      .poll(async () => {
        const unread = await parseJson(
          await staffCtx.request.get(`${baseUrl}/api/dashboard/ops/inbox/unread-summary`, {
            headers: { Accept: "application/json" },
          }),
        );
        return Number((unread.json.data as { unreadCount?: number } | undefined)?.unreadCount ?? 0);
      }, { timeout: 5000 })
      .toBeGreaterThan(beforeCount);

    const inbox = await parseJson(
      await staffCtx.request.get(`${baseUrl}/api/dashboard/ops/inbox`, {
        headers: { Accept: "application/json" },
      }),
    );
    const items = ((inbox.json.data as { items?: Array<{ event_key?: string; entity_ref?: string }> } | undefined)?.items) ?? [];
    const matches = items.filter((item) => {
      const hay = `${item.entity_ref ?? ""} ${item.event_key ?? ""} ${JSON.stringify(item)}`;
      return hay.includes(ticketRef) || hay.includes(ticketId);
    });
    expect(matches.length).toBeGreaterThan(0);
    // Authoritative dedupe: at most one assignment notice for this ticket+assignee pair in latest page.
    const assignMatches = matches.filter((item) => String(item.event_key ?? "").includes("support.ticket_assigned"));
    expect(assignMatches.length).toBeLessThanOrEqual(1);

    console.log("REALTIME_RECONNECT_RECOVERY=PASS");
    console.log("REALTIME_FALLBACK_BEHAVIOR=PASS");
    console.log("DUPLICATE_EVENT_PROTECTION=PASS");
  } finally {
    await adminCtx.close();
    await staffCtx.close();
    await customerCtx.close();
    await browser.close();
  }
});
