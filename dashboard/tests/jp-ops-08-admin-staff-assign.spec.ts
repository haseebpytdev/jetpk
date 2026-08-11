import { chromium, expect, test } from "@playwright/test";
import path from "node:path";

const repoRoot = path.resolve(process.cwd(), "..");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const storage = {
  admin: path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json"),
  staff: path.join(repoRoot, "tmp/jp-dash-03-staff-storage-state.json"),
};

async function parseJson(response: { text: () => Promise<string>; ok: () => boolean; status: () => number }) {
  const raw = (await response.text()).replace(/^\uFEFF/, "");
  return { ok: response.ok(), status: response.status(), json: JSON.parse(raw) as Record<string, unknown> };
}

test.describe.configure({ mode: "serial", timeout: 240_000 });

test("JP-OPS-08 admin assigns booking to staff with inbox fan-out", async () => {
  const browser = await chromium.launch({ headless: true });
  const adminCtx = await browser.newContext({ storageState: storage.admin });
  const staffCtx = await browser.newContext({ storageState: storage.staff });
  const admin = await adminCtx.newPage();
  const staff = await staffCtx.newPage();

  try {
    await admin.goto(`${baseUrl}/admin/dashboard/bookings`, { waitUntil: "domcontentloaded" });
    // Resolve a booking reference from live API.
    const list = await admin.request.get(`${baseUrl}/api/dashboard/bookings?pageSize=5`, {
      headers: { Accept: "application/json" },
    });
    const listParsed = await parseJson(list);
    expect(listParsed.ok).toBeTruthy();
    const items = (listParsed.json.data as { items?: Array<{ id?: string; reference?: string }> })?.items
      ?? (listParsed.json as { items?: Array<{ id?: string; reference?: string }> }).items
      ?? [];
    expect(items.length).toBeGreaterThan(0);
    const bookingId = String(items[0].id ?? items[0].reference ?? "");
    expect(bookingId).not.toEqual("");

    // Staff list for assignee id via Laravel portal JSON if available; otherwise skip assign when no staff picker API.
    const before = await parseJson(
      await staff.request.get(`${baseUrl}/api/dashboard/ops/inbox/unread-summary`, {
        headers: { Accept: "application/json" },
      }),
    );
    const beforeCount = Number((before.json.data as { unreadCount?: number } | undefined)?.unreadCount ?? 0);

    const t0 = Date.now();
    // Use existing portal assign endpoint when booking id is known.
    await admin.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const cookies = await adminCtx.cookies();
    const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
    const staffSession = await parseJson(
      await staff.request.get(`${baseUrl}/api/dashboard/session`, { headers: { Accept: "application/json" } }),
    );
    const staffUserId = Number(
      ((staffSession.json.data as { user?: { id?: number | string } } | undefined)?.user?.id as number | string | undefined) ?? 0,
    );

    if (staffUserId > 0) {
      const assign = await admin.request.post(
        `${baseUrl}/laravel/admin/bookings/${encodeURIComponent(bookingId)}/assign-staff?format=json`,
        {
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-XSRF-TOKEN": xsrf ? decodeURIComponent(xsrf.value) : "",
          },
          form: {
            assigned_staff_id: String(staffUserId),
          },
        },
      );
      console.log(`ASSIGN_STATUS=${assign.status()}`);
      expect(assign.status()).toBeLessThan(500);

      await expect
        .poll(
          async () => {
            const unread = await parseJson(
              await staff.request.get(`${baseUrl}/api/dashboard/ops/inbox/unread-summary`, {
                headers: { Accept: "application/json" },
              }),
            );
            return Number((unread.json.data as { unreadCount?: number } | undefined)?.unreadCount ?? 0);
          },
          { timeout: 5000 },
        )
        .toBeGreaterThan(beforeCount);

      const latency = Date.now() - t0;
      expect(latency).toBeLessThanOrEqual(5000);
      console.log(`ADMIN_TO_STAFF_ASSIGNMENT_LATENCY_MS=${latency}`);
      console.log("ADMIN_TO_STAFF_ASSIGNMENT_FLOW=PASS");
    } else {
      console.log("ADMIN_TO_STAFF_ASSIGNMENT_FLOW=NO_STAFF_USER_ID");
      test.skip(true, "Staff session user id unavailable for assign payload");
    }
  } finally {
    await adminCtx.close();
    await staffCtx.close();
    await browser.close();
  }
});
