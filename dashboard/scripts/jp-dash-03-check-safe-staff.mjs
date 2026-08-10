/**
 * Read-only production check for safe Staff acceptance identity availability.
 * Never prints email, phone, credentials, or PII.
 */
import { request } from "@playwright/test";
import {
  baseUrl,
  getStoragePath,
  storageStateExists,
} from "./jp-dash-03-acceptance/auth-storage.mjs";
import { checkOrRecoverSession } from "./jp-dash-03-acceptance/remember-recovery.mjs";

async function main() {
  if (!storageStateExists("admin")) {
    console.log("SAFE_STAFF_ACCOUNT_AVAILABLE=unknown_admin_session_missing");
    process.exit(2);
  }

  const adminStatus = await checkOrRecoverSession("admin");
  if (adminStatus !== "READY" && adminStatus !== "RECOVERED_FROM_REMEMBER") {
    console.log("SAFE_STAFF_ACCOUNT_AVAILABLE=unknown_admin_session_stale");
    process.exit(2);
  }

  const ctx = await request.newContext({
    baseURL: baseUrl,
    storageState: getStoragePath("admin"),
  });

  try {
    const response = await ctx.get("/admin/staff", {
      timeout: 120_000,
      headers: { Accept: "text/html" },
    });

    if (!response.ok()) {
      console.log("SAFE_STAFF_ACCOUNT_AVAILABLE=no");
      process.exit(1);
    }

    const body = await response.text();
    const hasStaffTable = /staff|Staff/i.test(body) && !/no staff|empty/i.test(body.slice(0, 5000));
    const hasActiveRows = (body.match(/data-testid="staff-/g) ?? []).length > 0
      || (body.match(/<tr/g) ?? []).length > 2;

    if (hasStaffTable && hasActiveRows) {
      console.log("SAFE_STAFF_ACCOUNT_AVAILABLE=yes");
      process.exit(0);
    }

    console.log("SAFE_STAFF_ACCOUNT_AVAILABLE=no");
    process.exit(1);
  } finally {
    await ctx.dispose();
  }
}

main().catch(() => {
  console.log("SAFE_STAFF_ACCOUNT_AVAILABLE=unknown");
  process.exit(2);
});
