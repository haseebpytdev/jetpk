/**
 * Shared reachable-route enumeration for FINAL-11 roles (read-only).
 */
import {
  ADMIN_SUPPLEMENTAL,
  AGENT_ROUTES,
  CUSTOMER_ROUTES,
  STAFF_SUPPLEMENTAL,
} from "./route-catalog.mjs";
import { FINAL11_AUTH_ROLES, FINAL11_ROLE_KEYS } from "./final-11-auth-roles.mjs";

export function roleMeta(roleKey) {
  const role = FINAL11_AUTH_ROLES[roleKey];
  return {
    portal: role.portal,
    accountType: role.accountType,
    agencyRole: role.agencyRole,
    qaIdentityKey: role.qaIdentityKey,
    home:
      role.qaIdentityKey === "legacy_agency_admin"
        ? "/account/legacy"
        : role.postLoginPath,
  };
}

export function staticRoutesForRole(roleKey) {
  if (roleKey === "platform_admin") return [...new Set(ADMIN_SUPPLEMENTAL)];
  if (roleKey === "staff") return [...new Set(["/staff/dashboard", ...STAFF_SUPPLEMENTAL])];
  if (roleKey === "legacy_agency_admin") return ["/account/legacy"];
  if (roleKey.startsWith("agent_")) return AGENT_ROUTES;
  if (roleKey.startsWith("customer_")) return CUSTOMER_ROUTES;
  return [];
}

export function filterNavHrefs(roleKey, hrefs) {
  return hrefs.filter((h) => {
    if (roleKey === "platform_admin") return h.startsWith("/admin/");
    if (roleKey === "staff") return h.startsWith("/staff/");
    if (roleKey.startsWith("agent_")) return h.startsWith("/agent/");
    if (roleKey.startsWith("customer_")) return h.startsWith("/customer/");
    if (roleKey === "legacy_agency_admin") return h.startsWith("/account/");
    return false;
  });
}

export function resolveFinalRoles() {
  const only = process.env.JP_FINAL_RESPONSIVE_ONLY?.trim();
  if (only) {
    if (!FINAL11_AUTH_ROLES[only]) throw new Error(`JP_FINAL_RESPONSIVE_ONLY invalid: ${only}`);
    return [only];
  }
  const list = process.env.JP_FINAL_RESPONSIVE_ROLES?.trim();
  if (list) {
    return list.split(",").map((r) => r.trim()).filter(Boolean);
  }
  return FINAL11_ROLE_KEYS;
}
