/**
 * Local JP-DASH-03 acceptance session health + keepalive.
 * Read-only pings — never logs cookies, credentials, or storageState contents.
 */
import { request } from "@playwright/test";
import {
  baseUrl,
  getStoragePath,
  storageStateExists,
} from "./auth-storage.mjs";
import { checkOrRecoverSession, checkSessionHealth } from "./remember-recovery.mjs";

const KEEPALIVE_PATH = "/api/dashboard/overview";
const KEEPALIVE_INTERVAL_MS = Number(process.env.JP_ACCEPTANCE_KEEPALIVE_MS ?? 4 * 60 * 1000);

let keepaliveTimer = null;
let staleDetected = false;

export { getStoragePath, storageStateExists, baseUrl };

/**
 * @returns {Promise<"READY"|"STALE"|"MISSING"|"RECOVERED_FROM_REMEMBER"|"REAUTH_REQUIRED">}
 */
export async function checkAdminSessionHealth() {
  return checkSessionHealth("admin");
}

/**
 * Attempt remember recovery when stale.
 * @returns {Promise<"READY"|"RECOVERED_FROM_REMEMBER"|"REAUTH_REQUIRED"|"MISSING">}
 */
export async function checkOrRecoverAdminSession() {
  return checkOrRecoverSession("admin");
}

export async function pingAdminSession() {
  const status = await checkAdminSessionHealth();
  if (status !== "READY") {
    staleDetected = true;
    stopAcceptanceSessionKeepalive();
    console.error(`ADMIN_PLAYWRIGHT_SESSION=${status}`);
    return status;
  }
  return "READY";
}

export function startAcceptanceSessionKeepalive() {
  if (keepaliveTimer || staleDetected) {
    return;
  }
  if (!storageStateExists("admin")) {
    return;
  }

  keepaliveTimer = setInterval(() => {
    pingAdminSession().catch(() => {
      staleDetected = true;
      stopAcceptanceSessionKeepalive();
      console.error("ADMIN_PLAYWRIGHT_SESSION=STALE");
    });
  }, KEEPALIVE_INTERVAL_MS);

  if (typeof keepaliveTimer.unref === "function") {
    keepaliveTimer.unref();
  }
}

export function stopAcceptanceSessionKeepalive() {
  if (keepaliveTimer) {
    clearInterval(keepaliveTimer);
    keepaliveTimer = null;
  }
}

export function isAcceptanceSessionStale() {
  return staleDetected;
}
