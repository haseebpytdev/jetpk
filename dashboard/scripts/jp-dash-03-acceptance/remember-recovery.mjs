/**
 * Attempt Laravel remember-cookie session recovery for acceptance storageState.
 * Never logs cookie values, credentials, or storageState contents.
 */
import { chromium } from "@playwright/test";
import {
  AUTH_ROLES,
  ensureStorageDir,
  getStoragePath,
  logRememberCookieMetadata,
  storageStateExists,
} from "./auth-storage.mjs";

const KEEPALIVE_PATH = "/api/dashboard/overview";
const SESSION_UNAVAILABLE = /Session unavailable|Dashboard unavailable|Dashboard temporarily unavailable/i;
const PREVIEW_LABEL = /preview/i;

/**
 * @returns {Promise<"READY"|"STALE"|"MISSING">}
 */
export async function checkSessionHealth(role = "admin") {
  if (!storageStateExists(role)) {
    return "MISSING";
  }

  const { request } = await import("@playwright/test");
  const ctx = await request.newContext({
    baseURL: AUTH_ROLES[role] ? (process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk") : undefined,
    storageState: getStoragePath(role),
  });

  try {
    const response = await ctx.get(KEEPALIVE_PATH, {
      timeout: 60_000,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    if (response.status() === 401 || response.status() === 403 || !response.ok()) {
      return "STALE";
    }
    return "READY";
  } finally {
    await ctx.dispose();
  }
}

/**
 * Browser-based remember recovery when API session is stale.
 * @returns {Promise<"RECOVERED_FROM_REMEMBER"|"REAUTH_REQUIRED"|"MISSING">}
 */
export async function attemptRememberRecovery(role = "admin") {
  if (!storageStateExists(role)) {
    return "MISSING";
  }

  const config = AUTH_ROLES[role] ?? AUTH_ROLES.admin;
  const storagePath = ensureStorageDir(role);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: storagePath });
  const page = await context.newPage();

  try {
    const response = await page.goto(config.dashboardPath, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });

    if ((response?.status() ?? 0) >= 400) {
      return "REAUTH_REQUIRED";
    }

    const body = await page.locator("body").innerText();
    if (SESSION_UNAVAILABLE.test(body)) {
      return "REAUTH_REQUIRED";
    }

    const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
    if (!portalLabel || PREVIEW_LABEL.test(portalLabel)) {
      return "REAUTH_REQUIRED";
    }

    if (!config.portalLabelPattern.test(portalLabel)) {
      return "REAUTH_REQUIRED";
    }

    await context.storageState({ path: storagePath });
    return "RECOVERED_FROM_REMEMBER";
  } catch {
    return "REAUTH_REQUIRED";
  } finally {
    await browser.close();
  }
}

/**
 * Check session; on stale, attempt remember recovery and re-save storageState.
 * @returns {Promise<"READY"|"RECOVERED_FROM_REMEMBER"|"REAUTH_REQUIRED"|"MISSING">}
 */
export async function checkOrRecoverSession(role = "admin") {
  const initial = await checkSessionHealth(role);
  if (initial === "READY") {
    return "READY";
  }
  if (initial === "MISSING") {
    return "MISSING";
  }

  const recovery = await attemptRememberRecovery(role);
  if (recovery !== "RECOVERED_FROM_REMEMBER") {
    return recovery === "MISSING" ? "MISSING" : "REAUTH_REQUIRED";
  }

  const after = await checkSessionHealth(role);
  if (after === "READY") {
    return "RECOVERED_FROM_REMEMBER";
  }

  return "REAUTH_REQUIRED";
}

export async function assertRememberEnabledAfterLogin(context, prefix = "ADMIN") {
  const cookies = await context.cookies();
  return logRememberCookieMetadata(cookies, prefix);
}
