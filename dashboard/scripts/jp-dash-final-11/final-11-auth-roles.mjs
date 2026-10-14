/**
 * JP-DASH-FINAL-11 authenticated identity contract (13 roles).
 */
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const repoRoot = path.resolve(__dirname, "../../../");

export const BASE_URL = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";

/**
 * @typedef {object} Final11AuthRole
 * @property {string} qaIdentityKey
 * @property {string} email
 * @property {"dash03"|"final11"} passwordSource
 * @property {string} [dash03VaultRole]
 * @property {string} [final11PasswordKey]
 * @property {string} storageFile
 * @property {string} postLoginPath
 * @property {RegExp} destinationPattern
 * @property {string} authResultKey
 * @property {string} portal
 * @property {string} accountType
 * @property {string} agencyRole
 */

/** @type {Record<string, Final11AuthRole>} */
export const FINAL11_AUTH_ROLES = {
  platform_admin: {
    qaIdentityKey: "platform_admin",
    email: "jp-dash-03-qa-admin@jetpakistan.pk",
    passwordSource: "dash03",
    dash03VaultRole: "admin",
    storageFile: "tmp/jp-final-11-platform-admin-storage-state.json",
    postLoginPath: "/admin/dashboard",
    destinationPattern: /\/admin\/dashboard/,
    authResultKey: "AUTH_PLATFORM_ADMIN",
    portal: "admin",
    accountType: "platform_admin",
    agencyRole: "",
  },
  staff: {
    qaIdentityKey: "staff",
    email: "jp-dash-03-qa-staff@jetpakistan.pk",
    passwordSource: "dash03",
    dash03VaultRole: "staff",
    storageFile: "tmp/jp-final-11-staff-storage-state.json",
    postLoginPath: "/staff/dashboard",
    destinationPattern: /\/staff\/dashboard/,
    authResultKey: "AUTH_STAFF",
    portal: "staff",
    accountType: "staff",
    agencyRole: "",
  },
  agent_owner_a: {
    qaIdentityKey: "agent_owner_a",
    email: "jp-dash-03-qa-agent@jetpakistan.pk",
    passwordSource: "dash03",
    dash03VaultRole: "agent",
    storageFile: "tmp/jp-final-11-agent-owner-a-storage-state.json",
    postLoginPath: "/agent/dashboard",
    destinationPattern: /\/agent\//,
    authResultKey: "AUTH_AGENT_OWNER_A",
    portal: "agent",
    accountType: "agent",
    agencyRole: "owner",
  },
  agent_owner_b: {
    qaIdentityKey: "agent_owner_b",
    email: "jp-final-11-qa-agent-b@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "agent_owner_b",
    storageFile: "tmp/jp-final-11-agent-owner-b-storage-state.json",
    postLoginPath: "/agent/dashboard",
    destinationPattern: /\/agent\//,
    authResultKey: "AUTH_AGENT_OWNER_B",
    portal: "agent",
    accountType: "agent",
    agencyRole: "owner",
  },
  agent_staff_manager: {
    qaIdentityKey: "agent_staff_manager",
    email: "jp-final-11-qa-staff-manager@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "agent_staff_manager",
    storageFile: "tmp/jp-final-11-agent-staff-manager-storage-state.json",
    postLoginPath: "/agent/dashboard",
    destinationPattern: /\/agent\//,
    authResultKey: "AUTH_AGENT_STAFF_MANAGER",
    portal: "agent",
    accountType: "agent_staff",
    agencyRole: "manager",
  },
  agent_staff_accountant: {
    qaIdentityKey: "agent_staff_accountant",
    email: "jp-final-11-qa-staff-accountant@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "agent_staff_accountant",
    storageFile: "tmp/jp-final-11-agent-staff-accountant-storage-state.json",
    postLoginPath: "/agent/dashboard",
    destinationPattern: /\/agent\//,
    authResultKey: "AUTH_AGENT_STAFF_ACCOUNTANT",
    portal: "agent",
    accountType: "agent_staff",
    agencyRole: "accountant",
  },
  agent_staff_sales: {
    qaIdentityKey: "agent_staff_sales",
    email: "jp-final-11-qa-staff-sales@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "agent_staff_sales",
    storageFile: "tmp/jp-final-11-agent-staff-sales-storage-state.json",
    postLoginPath: "/agent/dashboard",
    destinationPattern: /\/agent\//,
    authResultKey: "AUTH_AGENT_STAFF_SALES",
    portal: "agent",
    accountType: "agent_staff",
    agencyRole: "sales_agent",
  },
  agent_staff_support: {
    qaIdentityKey: "agent_staff_support",
    email: "jp-final-11-qa-staff-support@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "agent_staff_support",
    storageFile: "tmp/jp-final-11-agent-staff-support-storage-state.json",
    postLoginPath: "/agent/dashboard",
    destinationPattern: /\/agent\//,
    authResultKey: "AUTH_AGENT_STAFF_SUPPORT",
    portal: "agent",
    accountType: "agent_staff",
    agencyRole: "support_staff",
  },
  agent_staff_ticketing: {
    qaIdentityKey: "agent_staff_ticketing",
    email: "jp-final-11-qa-staff-ticketing@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "agent_staff_ticketing",
    storageFile: "tmp/jp-final-11-agent-staff-ticketing-storage-state.json",
    postLoginPath: "/agent/dashboard",
    destinationPattern: /\/agent\//,
    authResultKey: "AUTH_AGENT_STAFF_TICKETING",
    portal: "agent",
    accountType: "agent_staff",
    agencyRole: "ticketing_staff",
  },
  agent_staff_viewer: {
    qaIdentityKey: "agent_staff_viewer",
    email: "jp-final-11-qa-staff-viewer@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "agent_staff_viewer",
    storageFile: "tmp/jp-final-11-agent-staff-viewer-storage-state.json",
    postLoginPath: "/agent/dashboard",
    destinationPattern: /\/agent\//,
    authResultKey: "AUTH_AGENT_STAFF_VIEWER",
    portal: "agent",
    accountType: "agent_staff",
    agencyRole: "viewer",
  },
  customer_a: {
    qaIdentityKey: "customer_a",
    email: "jp-dash-03-qa-customer@jetpakistan.pk",
    passwordSource: "dash03",
    dash03VaultRole: "customer",
    storageFile: "tmp/jp-final-11-customer-a-storage-state.json",
    postLoginPath: "/customer/dashboard",
    destinationPattern: /\/customer\//,
    authResultKey: "AUTH_CUSTOMER_A",
    portal: "customer",
    accountType: "customer",
    agencyRole: "",
  },
  customer_b: {
    qaIdentityKey: "customer_b",
    email: "jp-final-11-qa-customer-b@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "customer_b",
    storageFile: "tmp/jp-final-11-customer-b-storage-state.json",
    postLoginPath: "/customer/dashboard",
    destinationPattern: /\/customer\//,
    authResultKey: "AUTH_CUSTOMER_B",
    portal: "customer",
    accountType: "customer",
    agencyRole: "",
  },
  legacy_agency_admin: {
    qaIdentityKey: "legacy_agency_admin",
    email: "jp-final-11-qa-legacy-agency-admin@jetpakistan.pk",
    passwordSource: "final11",
    final11PasswordKey: "legacy_agency_admin",
    storageFile: "tmp/jp-final-11-legacy-agency-admin-storage-state.json",
    postLoginPath: "/account/legacy",
    destinationPattern: /\/account\/legacy/,
    authResultKey: "AUTH_LEGACY_AGENCY_ADMIN",
    portal: "legacy",
    accountType: "agency_admin",
    agencyRole: "legacy",
  },
};

export const FINAL11_ROLE_KEYS = Object.keys(FINAL11_AUTH_ROLES);

export function storagePathForRole(roleKey) {
  const role = FINAL11_AUTH_ROLES[roleKey];
  return path.join(repoRoot, role.storageFile);
}

export function buildStorageMap() {
  /** @type {Record<string, string>} */
  const map = {};
  for (const key of FINAL11_ROLE_KEYS) {
    map[key] = storagePathForRole(key);
  }
  return map;
}
