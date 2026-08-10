/**
 * Local JP-DASH-03 acceptance session health + keepalive.
 * Read-only pings — never logs cookies, credentials, or storageState contents.
 */
import { request } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const defaultStoragePath = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");

const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const KEEPALIVE_PATH = "/api/dashboard/overview";
const KEEPALIVE_INTERVAL_MS = Number(process.env.JP_ACCEPTANCE_KEEPALIVE_MS ?? 4 * 60 * 1000);

let keepaliveTimer = null;
let keepaliveContext = null;
let staleDetected = false;

export function getStoragePath() {
  return process.env.JP_ADMIN_STORAGE_STATE ?? defaultStoragePath;
}

export function storageStateExists() {
  return fs.existsSync(getStoragePath());
}

/**
 * @returns {Promise<"READY"|"STALE"|"MISSING">}
 */
export async function checkAdminSessionHealth() {
  if (!storageStateExists()) {
    return "MISSING";
  }

  const ctx = await request.newContext({
    baseURL: baseUrl,
    storageState: getStoragePath(),
  });

  try {
    const response = await ctx.get(KEEPALIVE_PATH, {
      timeout: 60_000,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    if (response.status() === 401 || response.status() === 403) {
      return "STALE";
    }
    if (!response.ok()) {
      return "STALE";
    }
    return "READY";
  } finally {
    await ctx.dispose();
  }
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
  if (!storageStateExists()) {
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
  if (keepaliveContext) {
    keepaliveContext.dispose().catch(() => undefined);
    keepaliveContext = null;
  }
}

export function isAcceptanceSessionStale() {
  return staleDetected;
}
