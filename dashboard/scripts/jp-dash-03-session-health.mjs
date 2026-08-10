/**
 * Quick Admin session health check with remember-cookie recovery for JP-DASH-03.
 * Never prints cookies, passwords, OTP, or storageState contents.
 */
import { getStoragePath, storageStateExists } from "./jp-dash-03-acceptance/auth-storage.mjs";
import { checkOrRecoverSession } from "./jp-dash-03-acceptance/remember-recovery.mjs";

async function main() {
  if (!storageStateExists("admin")) {
    console.log("ADMIN_PLAYWRIGHT_SESSION=MISSING");
    process.exit(2);
  }

  const status = await checkOrRecoverSession("admin");
  console.log(`ADMIN_PLAYWRIGHT_SESSION=${status}`);
  process.exit(status === "READY" || status === "RECOVERED_FROM_REMEMBER" ? 0 : 1);
}

main().catch(() => {
  console.log("ADMIN_PLAYWRIGHT_SESSION=REAUTH_REQUIRED");
  process.exit(1);
});
