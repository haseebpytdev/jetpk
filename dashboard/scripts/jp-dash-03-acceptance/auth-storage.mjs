/**
 * Local-only Playwright storage paths for JP-DASH-03 acceptance.
 * Never log, stage, or commit storageState contents.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

export const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";

/** @type {Record<string, { storageEnv: string, defaultPath: string, dashboardPattern: RegExp, dashboardPath: string, portalLabelPattern: RegExp, loginUrl: string, qaLogin: string }>} */
export const AUTH_ROLES = {
  admin: {
    storageEnv: "JP_ADMIN_STORAGE_STATE",
    defaultPath: path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json"),
    dashboardPattern: /\/admin\/dashboard/,
    dashboardPath: "/admin/dashboard",
    portalLabelPattern: /admin/i,
    loginUrl: `${baseUrl}/login`,
    qaLogin: "jp-dash-03-qa-admin@jetpakistan.pk",
  },
  staff: {
    storageEnv: "JP_STAFF_STORAGE_STATE",
    defaultPath: path.join(repoRoot, "tmp/jp-dash-03-staff-storage-state.json"),
    dashboardPattern: /\/staff\/dashboard/,
    dashboardPath: "/staff/dashboard",
    portalLabelPattern: /staff/i,
    loginUrl: `${baseUrl}/login`,
    qaLogin: "jp-dash-03-qa-staff@jetpakistan.pk",
  },
  agent: {
    storageEnv: "JP_AGENT_STORAGE_STATE",
    defaultPath: path.join(repoRoot, "tmp/jp-dash-03-agent-storage-state.json"),
    dashboardPattern: /\/agent\/dashboard/,
    dashboardPath: "/agent/dashboard",
    portalLabelPattern: /agent/i,
    loginUrl: `${baseUrl}/login`,
    qaLogin: "jp-dash-03-qa-agent@jetpakistan.pk",
  },
  customer: {
    storageEnv: "JP_CUSTOMER_STORAGE_STATE",
    defaultPath: path.join(repoRoot, "tmp/jp-dash-03-customer-storage-state.json"),
    dashboardPattern: /\/customer\/(dashboard|bookings|support|account)/,
    dashboardPath: "/customer/dashboard",
    portalLabelPattern: /customer/i,
    loginUrl: `${baseUrl}/login`,
    qaLogin: "jp-dash-03-qa-customer@jetpakistan.pk",
  },
};

export function getStoragePath(role = "admin") {
  const config = AUTH_ROLES[role] ?? AUTH_ROLES.admin;
  return process.env[config.storageEnv] ?? config.defaultPath;
}

export function storageStateExists(role = "admin") {
  return fs.existsSync(getStoragePath(role));
}

export function ensureStorageDir(role = "admin") {
  const storagePath = getStoragePath(role);
  fs.mkdirSync(path.dirname(storagePath), { recursive: true });
  return storagePath;
}

/**
 * Inspect remember-cookie metadata only — never print values.
 */
export function summarizeRememberCookies(cookies) {
  const rememberCookies = cookies.filter(
    (cookie) => cookie.name.startsWith("remember_") || cookie.name.includes("remember"),
  );
  const nowSec = Date.now() / 1000;
  const persistentExpiryPresent = rememberCookies.some(
    (cookie) => cookie.expires > 0 && cookie.expires > nowSec + 7 * 24 * 60 * 60,
  );

  return {
    rememberCookiePresent: rememberCookies.length > 0,
    persistentExpiryPresent,
    rememberCookieCount: rememberCookies.length,
  };
}

export function logRememberCookieMetadata(cookies, prefix = "ADMIN") {
  const summary = summarizeRememberCookies(cookies);
  console.log(`${prefix}_REMEMBER_COOKIE_PRESENT=${summary.rememberCookiePresent ? "yes" : "no"}`);
  console.log(`${prefix}_PERSISTENT_EXPIRY_PRESENT=${summary.persistentExpiryPresent ? "yes" : "no"}`);
  return summary;
}
