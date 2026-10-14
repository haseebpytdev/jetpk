/**
 * Deep wave 03 reversible semantics (canonical persistence recount).
 */
export function inferDomain(row) {
  const route = String(row.ROUTE || "");
  if (route.includes("/profile")) return "PROFILE";
  if (route.includes("/security")) return "SECURITY";
  if (route.includes("/booking")) return "BOOKING";
  if (route.includes("/staff/new")) return "STAFF";
  if (route.includes("/travelers/new")) return "TRAVELER";
  if (route.includes("/cms")) return "CMS";
  if (route.includes("/support")) return "SUPPORT";
  if (route.includes("/deposit")) return "DEPOSIT";
  if (route.includes("/wallet") || route.includes("/ledger")) return "WALLET";
  return "OTHER";
}

export const BLADE_SAVE_PROFILE_CASE_IDS = [
  "FC-005424",
  "FC-006831",
  "FC-008186",
  "FC-009477",
  "FC-010648",
  "FC-011738",
  "FC-012824",
  "FC-013911",
  "FC-014389",
  "FC-014952",
];

export function classifyReversibleSemanticV3(row) {
  const label = String(row.CONTROL_LABEL || "");
  const route = String(row.ROUTE || "");
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const l = label.toLowerCase();

  if (/theme:/i.test(label)) {
    return {
      persistence: "CLIENT_LOCAL_STATE",
      recommended: "QA_REVERSIBLE_MUTATION",
      restore: "localStorage_theme_toggle_back",
      audit: "NOT_REQUIRED",
      risk: "NONE",
      safe: "YES",
      reason: "client_theme_preference",
      qaOnly: "YES",
    };
  }

  if ((label === "Save profile" || l === "save profile") && type === "button") {
    return {
      persistence: "SERVER_QA_DB",
      recommended: "QA_REVERSIBLE_MUTATION",
      restore: "revert_profile_whatsapp_and_save",
      audit: "OPTIONAL",
      risk: "LOW",
      safe: "YES",
      reason: route.includes("/agent/profile") || route.includes("/customer/profile")
        ? "qa_blade_portal_profile_field_persist"
        : "qa_next_dashboard_profile_field_persist",
      qaOnly: "YES",
    };
  }

  if (/update profile/i.test(label)) {
    if (type === "navigation_link") {
      return {
        persistence: "NO_REAL_MUTATION",
        recommended: "NAVIGATION",
        restore: "NONE",
        audit: "NOT_APPLICABLE",
        risk: "LOW",
        safe: "NO",
        reason: "profile_nav_link_not_mutation",
        qaOnly: "YES",
      };
    }
    return {
      persistence: "SERVER_QA_DB",
      recommended: "QA_REVERSIBLE_MUTATION",
      restore: "revert_profile_field_and_save",
      audit: "OPTIONAL",
      risk: "LOW",
      safe: "YES",
      reason: "profile_update_action",
      qaOnly: "YES",
    };
  }

  if (/change password/i.test(label) || route.includes("/security")) {
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "NOT_APPLICABLE",
      risk: "HIGH",
      safe: "NO",
      reason: "security_credential_surface",
      qaOnly: "YES",
    };
  }

  if (/booking-cancellation|booking-note-input|^textarea$/i.test(label) && /booking/i.test(route)) {
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "REQUIRED",
      risk: "HIGH",
      safe: "NO",
      reason: "booking_operational_mutation",
      qaOnly: "NO",
    };
  }

  if (
    /publish|upload media|assign permissions|suspend|approve|reject|void|refund|ticket|pay now|submit deposit/i.test(
      label,
    )
  ) {
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "CASE_BY_CASE",
      risk: "HIGH",
      safe: "NO",
      reason: "external_or_high_risk_action",
      qaOnly: "NO",
    };
  }

  if (type === "textarea") {
    if (/cms/i.test(route)) {
      return {
        persistence: "SERVER_QA_DB",
        recommended: "QA_REVERSIBLE_MUTATION",
        restore: "revert_cms_draft_field",
        audit: "OPTIONAL",
        risk: "LOW",
        safe: "REVIEW",
        reason: "cms_draft_textarea",
        qaOnly: "YES",
      };
    }
    return {
      persistence: "EXTERNAL_SIDE_EFFECT",
      recommended: "EXTERNAL_ACTION_GATED",
      restore: "NONE",
      audit: "OPTIONAL",
      risk: "MEDIUM",
      safe: "NO",
      reason: "textarea_mutation_surface",
      qaOnly: "NO",
    };
  }

  if (type === "input_checkbox" && /staff-perm-/i.test(label)) {
    return {
      persistence: "SERVER_QA_DB",
      recommended: "QA_REVERSIBLE_MUTATION",
      restore: "toggle_permission_back",
      audit: "REQUIRED",
      risk: "MEDIUM",
      safe: "REVIEW",
      reason: "staff_permission_toggle",
      qaOnly: "YES",
    };
  }

  return {
    persistence: "NO_REAL_MUTATION",
    recommended: "READ_OPERATION",
    restore: "NONE",
    audit: "NOT_APPLICABLE",
    risk: "LOW",
    safe: "NO",
    reason: "interactive_shell_not_reversible_mutation",
    qaOnly: "YES",
  };
}

export function persistenceBucket(sem) {
  if (sem.persistence === "EXTERNAL_SIDE_EFFECT") return "CURRENT_EXTERNAL_SIDE_EFFECT";
  if (sem.persistence === "NO_REAL_MUTATION") return "CURRENT_NO_REAL_MUTATION";
  if (sem.persistence === "CLIENT_LOCAL_STATE") return "CURRENT_CLIENT_LOCAL_STATE";
  if (sem.persistence === "SERVER_QA_DB") {
    if (/cms/i.test(sem.reason)) return "CURRENT_SERVER_GLOBAL_CONFIG";
    return "CURRENT_SERVER_QA_DB";
  }
  return "CURRENT_UNKNOWN";
}
