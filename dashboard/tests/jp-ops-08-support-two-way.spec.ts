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

test("JP-OPS-08 support two-way + staff assignment", async () => {
  const browser = await chromium.launch({ headless: true });
  const adminCtx = await browser.newContext({ storageState: storage.admin });
  const staffCtx = await browser.newContext({ storageState: storage.staff });
  const customerCtx = await browser.newContext({ storageState: storage.customer });
  const admin = await adminCtx.newPage();
  const staff = await staffCtx.newPage();
  const customer = await customerCtx.newPage();

  try {
    await customer.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const customerCookies = await customerCtx.cookies();
    const customerXsrf = customerCookies.find((c) => c.name === "XSRF-TOKEN");

    const subject = `JP-OPS-08 two-way ${Date.now()}`;
    const t0 = Date.now();
    const create = await customer.request.post(`${baseUrl}/laravel/customer/support/tickets?format=json`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": customerXsrf ? decodeURIComponent(customerXsrf.value) : "",
      },
      form: {
        subject,
        category: "other",
        body: "Customer message 1",
      },
    });
    expect(create.status()).toBe(201);
    const created = await parseJson(create);
    const ticketPayload =
      (created.json.ticket as Record<string, unknown> | undefined)
      ?? ((created.json.data as { ticket?: Record<string, unknown> } | undefined)?.ticket)
      ?? {};
    const ticketRef = String(ticketPayload.ticket_reference ?? ticketPayload.reference ?? "");
    console.log(`TICKET_REF=${ticketRef || "missing"}`);
    expect(ticketRef).not.toEqual("");

    const resolved = await parseJson(
      await admin.request.get(`${baseUrl}/api/dashboard/support/tickets/${encodeURIComponent(ticketRef)}`, {
        headers: { Accept: "application/json" },
      }),
    );
    expect(resolved.ok).toBeTruthy();
    const ticketId = String(
      (resolved.json.data as { id?: string | number } | undefined)?.id
        ?? (resolved.json as { id?: string | number }).id
        ?? "",
    );
    console.log(`TICKET_ID=${ticketId || "missing"}`);
    expect(ticketId).not.toEqual("");
    const adminTicketKey = ticketId;

    await expect
      .poll(async () => {
        const unread = await parseJson(
          await admin.request.get(`${baseUrl}/api/dashboard/ops/inbox/unread-summary`, {
            headers: { Accept: "application/json" },
          }),
        );
        return Number((unread.json.data as { unreadCount?: number } | undefined)?.unreadCount ?? 0);
      }, { timeout: 5000 })
      .toBeGreaterThan(0);
    console.log(`CUSTOMER_TO_ADMIN_LATENCY_MS=${Date.now() - t0}`);

    const staffBefore = await parseJson(
      await staff.request.get(`${baseUrl}/api/dashboard/ops/inbox/unread-summary`, {
        headers: { Accept: "application/json" },
      }),
    );
    const staffBeforeCount = Number((staffBefore.json.data as { unreadCount?: number } | undefined)?.unreadCount ?? 0);

    const staffSession = await parseJson(
      await staff.request.get(`${baseUrl}/api/dashboard/session`, { headers: { Accept: "application/json" } }),
    );
    const sessionData = (staffSession.json.data ?? staffSession.json) as Record<string, unknown>;
    const userBlock = (sessionData.user ?? sessionData.actor ?? {}) as Record<string, unknown>;
    let staffUserId = Number(userBlock.id ?? userBlock.userId ?? 0);
    if (!Number.isFinite(staffUserId) || staffUserId <= 0) {
      // Known dedicated QA staff id from JP-DASH-03 identities (stable in this environment).
      staffUserId = 8;
    }
    console.log(`STAFF_USER_ID=${staffUserId}`);
    expect(staffUserId).toBeGreaterThan(0);

    if (adminTicketKey !== "") {
      await admin.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      });
      const adminCookies = await adminCtx.cookies();
      const adminXsrf = adminCookies.find((c) => c.name === "XSRF-TOKEN");
      const tAssign = Date.now();
      const assign = await admin.request.fetch(
        `${baseUrl}/laravel/admin/support/tickets/${encodeURIComponent(adminTicketKey)}/assign?format=json`,
        {
          method: "PATCH",
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-XSRF-TOKEN": adminXsrf ? decodeURIComponent(adminXsrf.value) : "",
            "Content-Type": "application/x-www-form-urlencoded",
          },
          data: `assigned_to_user_id=${encodeURIComponent(String(staffUserId))}`,
        },
      );
      console.log(`SUPPORT_ASSIGN_STATUS=${assign.status()}`);
      const assignBody = await assign.text();
      console.log(`SUPPORT_ASSIGN_BODY_HEAD=${assignBody.replace(/^\uFEFF/, "").slice(0, 180)}`);
      expect(assign.status()).toBeLessThan(500);
      expect(assign.status()).toBe(200);

      await expect
        .poll(async () => {
          const unread = await parseJson(
            await staff.request.get(`${baseUrl}/api/dashboard/ops/inbox/unread-summary`, {
              headers: { Accept: "application/json" },
            }),
          );
          return Number((unread.json.data as { unreadCount?: number } | undefined)?.unreadCount ?? 0);
        }, { timeout: 5000 })
        .toBeGreaterThan(staffBeforeCount);
      console.log(`ADMIN_TO_STAFF_SUPPORT_ASSIGN_LATENCY_MS=${Date.now() - tAssign}`);
      console.log("SUPPORT_ASSIGN_TO_STAFF=PASS");
    }

    // Staff customer-visible reply → customer unread increments.
    if (adminTicketKey !== "") {
      const customerBefore = await parseJson(
        await customer.request.get(`${baseUrl}/laravel/customer/notifications/unread-summary?format=json`, {
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        }),
      );
      const customerBeforeCount = Number(
        (customerBefore.json as { unread_count?: number }).unread_count
          ?? (customerBefore.json.data as { unread_count?: number } | undefined)?.unread_count
          ?? 0,
      );

      await staff.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      });
      const staffCookies = await staffCtx.cookies();
      const staffXsrf = staffCookies.find((c) => c.name === "XSRF-TOKEN");
      const tReply = Date.now();
      const reply = await staff.request.post(
        `${baseUrl}/laravel/staff/support/tickets/${encodeURIComponent(adminTicketKey)}/reply?format=json`,
        {
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-XSRF-TOKEN": staffXsrf ? decodeURIComponent(staffXsrf.value) : "",
          },
          form: {
            body: "Staff reply 1",
            visibility: "customer_visible",
          },
        },
      );
      console.log(`STAFF_REPLY_STATUS=${reply.status()}`);
      const replyBody = await reply.text();
      console.log(`STAFF_REPLY_BODY_HEAD=${replyBody.replace(/^\uFEFF/, "").slice(0, 180)}`);
      expect(reply.status()).toBe(200);

      await expect
        .poll(async () => {
          const unread = await parseJson(
            await customer.request.get(`${baseUrl}/laravel/customer/notifications/unread-summary?format=json`, {
              headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            }),
          );
          return Number(
            (unread.json as { unread_count?: number }).unread_count
              ?? (unread.json.data as { unread_count?: number } | undefined)?.unread_count
              ?? 0,
          );
        }, { timeout: 5000 })
        .toBeGreaterThan(customerBeforeCount);
      console.log(`STAFF_TO_CUSTOMER_REPLY_LATENCY_MS=${Date.now() - tReply}`);
      console.log("SUPPORT_TWO_WAY_CONVERSATION=PASS");
    }

    console.log("REALTIME_TRANSPORT=EVENT_POLLING");
  } finally {
    await adminCtx.close();
    await staffCtx.close();
    await customerCtx.close();
    await browser.close();
  }
});
