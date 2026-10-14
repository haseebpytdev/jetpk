/**
 * Authoritative Agent Staff RBAC expectations (mirrors PHP templates + presenter).
 * OWNER_ONLY_MATRIX_SOURCE=AUTHORITATIVE
 */

/** @type {Record<string, string>} */
export const PHP_TO_PRESENTER_PERMISSION = {
  "agent.bookings.view": "bookings_view",
  "agent.bookings.create": "bookings_create",
  "agent.wallet.view": "wallet_view",
  "agent.ledger.view": "ledger_view",
  "agent.ledger.manage": "ledger_manage",
  "agent.reports.view": "reports_view",
  "agent.payments.upload": "payments_upload",
  "agent.travelers.manage": "travelers_manage",
  "agent.support.manage": "support_manage",
  "agent.staff.manage": "staff_manage",
  "agent.agency.view": "agency_view",
};

/** @type {Record<string, string[]>} */
const SUGGESTED_PHP_PERMISSIONS = {
  manager: [
    "agent.bookings.view",
    "agent.bookings.create",
    "agent.wallet.view",
    "agent.ledger.view",
    "agent.ledger.manage",
    "agent.reports.view",
    "agent.payments.upload",
    "agent.travelers.manage",
    "agent.support.manage",
    "agent.staff.manage",
    "agent.agency.view",
  ],
  accountant: [
    "agent.bookings.view",
    "agent.wallet.view",
    "agent.ledger.view",
    "agent.ledger.manage",
    "agent.reports.view",
    "agent.payments.upload",
    "agent.agency.view",
  ],
  sales_agent: [
    "agent.bookings.view",
    "agent.bookings.create",
    "agent.travelers.manage",
    "agent.agency.view",
  ],
  support_staff: ["agent.bookings.view", "agent.support.manage", "agent.agency.view"],
  ticketing_staff: ["agent.bookings.view", "agent.agency.view"],
  viewer: ["agent.bookings.view", "agent.agency.view"],
};

const STAFF_SELECTABLE = new Set(
  Object.keys(PHP_TO_PRESENTER_PERMISSION).filter(
    (k) => k !== "agent.profile.manage" && k !== "agent.agency.edit",
  ),
);

/**
 * templatePermissionsForAgentStaff equivalent (excludes LedgerManage for manager/accountant).
 * @param {string} agencyRoleTemplate
 * @returns {string[]}
 */
export function expectedPresenterPermissions(agencyRoleTemplate) {
  const suggested = SUGGESTED_PHP_PERMISSIONS[agencyRoleTemplate] ?? [];
  const out = [];
  for (const phpKey of suggested) {
    if (!STAFF_SELECTABLE.has(phpKey)) continue;
    if (
      (agencyRoleTemplate === "manager" || agencyRoleTemplate === "accountant") &&
      phpKey === "agent.ledger.manage"
    ) {
      continue;
    }
    const presenterKey = PHP_TO_PRESENTER_PERMISSION[phpKey];
    if (presenterKey) out.push(presenterKey);
  }
  return [...new Set(out)];
}

/** Owner-exclusive capability flags (AgentPortalCapabilitiesPresenter). */
export const OWNER_ONLY_CAPABILITY_FLAGS = [
  "can_view_commission",
  "can_edit_agency",
  "can_manage_staff_permissions",
];

/** Direct URL probes that must deny for all agent_staff templates. */
export const OWNER_ONLY_URL_PROBES = [
  { path: "/agent/commissions", label: "OWNER_ONLY" },
  { path: "/agent/agency/edit", label: "OWNER_ONLY", json: true },
];

/**
 * Build expected nav codes from presenter rules (modules assumed enabled unless payload says otherwise).
 * @param {string[]} permissionKeys
 * @param {Record<string, boolean>} modules
 * @returns {string[]}
 */
export function expectedNavHrefs(permissionKeys, modules) {
  const p = new Set(permissionKeys);
  const m = (key) => modules[key] !== false;
  const hrefs = ["/agent/dashboard"];
  if (p.has("bookings_view")) hrefs.push("/agent/bookings");
  if (p.has("bookings_create")) hrefs.push("/agent/bookings/create");
  if (p.has("wallet_view") && m("agent_wallet")) hrefs.push("/agent/wallet");
  if (p.has("ledger_view") && m("agent_ledger")) {
    hrefs.push("/agent/wallet/ledger", "/agent/accounting/ledger");
  }
  if ((p.has("reports_view") || p.has("ledger_view")) && m("agent_reports")) {
    hrefs.push("/agent/finance/statement");
  }
  if (p.has("travelers_manage") && m("saved_travelers")) hrefs.push("/agent/travelers");
  if (p.has("payments_upload") && m("agent_deposits")) hrefs.push("/agent/deposits");
  if (p.has("wallet_view")) {
    hrefs.push("/agent/payments", "/agent/invoices");
  }
  if (p.has("reports_view") && m("agent_reports")) hrefs.push("/agent/reports");
  if (p.has("staff_manage") && m("agent_staff")) hrefs.push("/agent/staff");
  if (p.has("agency_view")) hrefs.push("/agent/agency");
  hrefs.push("/agent/profile", "/agent/security", "/agent/notifications");
  if (p.has("support_manage") && m("agent_support")) hrefs.push("/agent/support");
  return [...new Set(hrefs)];
}
