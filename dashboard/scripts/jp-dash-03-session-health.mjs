/**
 * Quick Admin session health check for JP-DASH-03 acceptance.
 * Never prints cookies, passwords, OTP, or storageState contents.
 */
import {
  checkAdminSessionHealth,
  getStoragePath,
  storageStateExists,
} from "./jp-dash-03-acceptance/session-keepalive.mjs";

async function main() {
  if (!storageStateExists()) {
    console.log("ADMIN_PLAYWRIGHT_SESSION=MISSING");
    process.exit(2);
  }

  const status = await checkAdminSessionHealth();
  console.log(`ADMIN_PLAYWRIGHT_SESSION=${status}`);
  process.exit(status === "READY" ? 0 : 1);
}

main().catch(() => {
  console.log("ADMIN_PLAYWRIGHT_SESSION=STALE");
  process.exit(1);
});
