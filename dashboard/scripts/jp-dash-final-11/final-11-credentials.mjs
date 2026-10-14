/**
 * FINAL-11 workstation password resolution (env / Windows Credential Manager).
 * Never logs password values.
 */
import { loadPasswordFromCredTargets, loadQaPasswordFromVault } from "../jp-dash-03-acceptance/credential-vault.mjs";

/** @type {Record<string, string[]>} */
export const FINAL11_VAULT_TARGETS = {
  agent_owner_b: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Agent-B",
    "JetPakistan-JP-FINAL-11-QA-Agent-B",
  ],
  customer_b: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Customer-B",
    "JetPakistan-JP-FINAL-11-QA-Customer-B",
  ],
  agent_staff_manager: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Staff-Manager",
    "JetPakistan-JP-FINAL-11-QA-Staff-Manager",
  ],
  agent_staff_accountant: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Staff-Accountant",
    "JetPakistan-JP-FINAL-11-QA-Staff-Accountant",
  ],
  agent_staff_sales: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Staff-Sales",
    "JetPakistan-JP-FINAL-11-QA-Staff-Sales",
  ],
  agent_staff_support: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Staff-Support",
    "JetPakistan-JP-FINAL-11-QA-Staff-Support",
  ],
  agent_staff_ticketing: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Staff-Ticketing",
    "JetPakistan-JP-FINAL-11-QA-Staff-Ticketing",
  ],
  agent_staff_viewer: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Staff-Viewer",
    "JetPakistan-JP-FINAL-11-QA-Staff-Viewer",
  ],
  legacy_agency_admin: [
    "LegacyGeneric:target=JetPakistan-JP-FINAL-11-QA-Legacy-Agency-Admin",
    "JetPakistan-JP-FINAL-11-QA-Legacy-Agency-Admin",
  ],
};

/** @type {Record<string, string>} */
export const FINAL11_PASSWORD_ENV_KEYS = {
  agent_owner_b: "JP_FINAL_11_QA_AGENT_B_PASSWORD",
  customer_b: "JP_FINAL_11_QA_CUSTOMER_B_PASSWORD",
  agent_staff_manager: "JP_FINAL_11_QA_STAFF_MANAGER_PASSWORD",
  agent_staff_accountant: "JP_FINAL_11_QA_STAFF_ACCOUNTANT_PASSWORD",
  agent_staff_sales: "JP_FINAL_11_QA_STAFF_SALES_PASSWORD",
  agent_staff_support: "JP_FINAL_11_QA_STAFF_SUPPORT_PASSWORD",
  agent_staff_ticketing: "JP_FINAL_11_QA_STAFF_TICKETING_PASSWORD",
  agent_staff_viewer: "JP_FINAL_11_QA_STAFF_VIEWER_PASSWORD",
  legacy_agency_admin: "JP_FINAL_11_QA_LEGACY_AGENCY_ADMIN_PASSWORD",
};

/**
 * @param {"admin"|"staff"|"agent"|"customer"} dash03Role
 * @returns {string|null}
 */
export function loadDash03Password(dash03Role) {
  return loadQaPasswordFromVault(dash03Role);
}

/**
 * @param {string} final11RoleKey
 * @returns {string|null}
 */
export function loadFinal11Password(final11RoleKey) {
  const envKey = FINAL11_PASSWORD_ENV_KEYS[final11RoleKey];
  if (envKey) {
    const value = process.env[envKey]?.trim();
    if (value) {
      return value;
    }
  }

  const targets = FINAL11_VAULT_TARGETS[final11RoleKey];
  if (targets) {
    const fromVault = loadPasswordFromCredTargets(targets);
    if (fromVault) {
      return fromVault;
    }
  }

  if (final11RoleKey.startsWith("agent_staff_")) {
    return loadQaPasswordFromVault("agentStaff");
  }

  return null;
}

/**
 * @param {string} roleKey
 * @param {"dash03"|"final11"} source
 * @param {string} [final11Key]
 * @returns {string}
 */
export function resolvePasswordForRole(roleKey, source, final11Key = roleKey) {
  const password =
    source === "dash03" ? loadDash03Password(roleKey) : loadFinal11Password(final11Key);
  if (!password) {
    throw new Error(`MISSING_PASSWORD:${roleKey}`);
  }
  return password;
}
