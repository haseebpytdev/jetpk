/**
 * Semantic typing for rows currently in FORM_VALIDATION.
 */
import { classifyFormValidationRisk, suggestedReclassForMutationRisk } from "./form-validation-risk.mjs";

const FILTER_SELECT =
  /^all|rows per page|one way|return|confirmed|pending|paid|unpaid|ticket|status|sabre|channel|supplier|category|severity|type/i;

export function classifyFormSemantic(row) {
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const label = String(row.CONTROL_LABEL || "");
  const l = label.toLowerCase();
  const route = String(row.ROUTE || "");

  const base = {
    SEMANTIC_TYPE: "UNKNOWN",
    PROBEABLE: "NO",
    PROBE_TARGET: "",
    RECOMMENDED_CLASS: "FORM_VALIDATION",
    REASON: "",
    PROBE_TARGET_KIND: "",
    PROBE_TARGET_SELECTOR_SAFE: "",
    INVALID_VALUE_KIND: "",
    EXPECTED_SIGNAL: "",
  };

  if (type === "select") {
    if (/password|otp|mfa|2fa|security code/i.test(l)) {
      return {
        ...base,
        SEMANTIC_TYPE: "FIELD_VALIDATION",
        PROBEABLE: "YES",
        PROBE_TARGET: "select:security",
        PROBE_TARGET_KIND: "select",
        PROBE_TARGET_SELECTOR_SAFE: "select:visible",
        INVALID_VALUE_KIND: "empty_required",
        EXPECTED_SIGNAL: "NATIVE_VALIDATION_BLOCK",
        REASON: "security_select_field",
      };
    }
    return {
      ...base,
      SEMANTIC_TYPE: "SEARCH_OR_FILTER_SHELL",
      RECOMMENDED_CLASS: "NOT_APPLICABLE",
      REASON: "misclassified_filter_select_inventory_shell",
    };
  }

  if (type === "navigation_link") {
    if (/login otp/i.test(l)) {
      if (route.includes("/dashboard") || route.includes("/agent/") || route.includes("/customer/")) {
        return {
          ...base,
          SEMANTIC_TYPE: "LOGIN_AUTH_SHELL",
          RECOMMENDED_CLASS: "NOT_APPLICABLE",
          REASON: "otp_shell_not_present_under_active_session",
        };
      }
      return {
        ...base,
        SEMANTIC_TYPE: "LOGIN_AUTH_SHELL",
        PROBEABLE: "YES",
        PROBE_TARGET: "otp_input",
        PROBE_TARGET_KIND: "input",
        PROBE_TARGET_SELECTOR_SAFE: "input[autocomplete='one-time-code'],input[name*='otp' i],input[inputmode='numeric']",
        INVALID_VALUE_KIND: "empty_required",
        EXPECTED_SIGNAL: "NATIVE_VALIDATION_BLOCK",
        REASON: "login_otp_field_probe",
      };
    }
    if (l === "security") {
      return {
        ...base,
        SEMANTIC_TYPE: "STRUCTURAL_FORM_ELEMENT",
        RECOMMENDED_CLASS: "NOT_APPLICABLE",
        REASON: "security_nav_link_not_form_validation",
      };
    }
    return {
      ...base,
      SEMANTIC_TYPE: "STRUCTURAL_FORM_ELEMENT",
      RECOMMENDED_CLASS: "NOT_APPLICABLE",
      REASON: "navigation_link_not_validation",
    };
  }

  if (type === "form") {
    if (/\b(export|download|csv|pdf)\b/i.test(l)) {
      return {
        ...base,
        SEMANTIC_TYPE: "EXTERNAL_ACTION",
        RECOMMENDED_CLASS: "DOWNLOAD_EXPORT",
        REASON: "export_form_surface",
      };
    }
    if (/search|filter|apply filter|reset filter/i.test(l) && l.length > 24) {
      return {
        ...base,
        SEMANTIC_TYPE: "SEARCH_OR_FILTER_SHELL",
        RECOMMENDED_CLASS: "NOT_APPLICABLE",
        REASON: "composite_filter_form_shell",
      };
    }
    if (/email verification|save profile|full name|phone|username/i.test(l)) {
      return {
        ...base,
        SEMANTIC_TYPE: "FORM_SUBMIT_VALIDATION",
        PROBEABLE: "YES",
        PROBE_TARGET: "profile_form",
        PROBE_TARGET_KIND: "form",
        PROBE_TARGET_SELECTOR_SAFE: "form:visible input[type='email'],form:visible input:visible",
        INVALID_VALUE_KIND: "malformed_email",
        EXPECTED_SIGNAL: "NATIVE_VALIDATION_BLOCK",
        REASON: "profile_form_fields_inside_container",
      };
    }
    if (/\/travelers\/new|\/staff\/new/i.test(route)) {
      return {
        ...base,
        SEMANTIC_TYPE: "MUTATION_ACTION",
        RECOMMENDED_CLASS: "QA_CREATE_DELETE_LIFECYCLE",
        REASON: "create_lifecycle_form_surface",
      };
    }
    if (/title|gender|traveler|passenger|mr mrs/i.test(l)) {
      return {
        ...base,
        SEMANTIC_TYPE: "MUTATION_ACTION",
        RECOMMENDED_CLASS: "QA_CREATE_DELETE_LIFECYCLE",
        REASON: "traveler_create_form_surface",
      };
    }
    return {
      ...base,
      SEMANTIC_TYPE: "COMPOSITE_FORM_CONTAINER",
      RECOMMENDED_CLASS: "NOT_APPLICABLE",
      REASON: "non_probeable_form_container",
    };
  }

  if (type === "button") {
    const risk = classifyFormValidationRisk(row);
    if (risk === "MUTATION_RISK") {
      const rec = suggestedReclassForMutationRisk(row);
      return {
        ...base,
        SEMANTIC_TYPE: "MUTATION_ACTION",
        RECOMMENDED_CLASS: rec,
        REASON: "submit_button_mutation_risk",
      };
    }
    return {
      ...base,
      SEMANTIC_TYPE: "MUTATION_ACTION",
      RECOMMENDED_CLASS: "NOT_APPLICABLE",
      REASON: "button_not_field_validation",
    };
  }

  if (type === "div") {
    return {
      ...base,
      SEMANTIC_TYPE: "STRUCTURAL_FORM_ELEMENT",
      RECOMMENDED_CLASS: "NOT_APPLICABLE",
      REASON: "div_layout_shell",
    };
  }

  if (type === "input_email" || type.includes("email")) {
    return {
      ...base,
      SEMANTIC_TYPE: "FIELD_VALIDATION",
      PROBEABLE: "YES",
      PROBE_TARGET: "email",
      PROBE_TARGET_KIND: "input_email",
      PROBE_TARGET_SELECTOR_SAFE: "input[type='email']:visible",
      INVALID_VALUE_KIND: "malformed_email",
      EXPECTED_SIGNAL: "NATIVE_VALIDATION_BLOCK",
      REASON: "email_field",
    };
  }

  if (type === "input_password" || type.includes("password")) {
    return {
      ...base,
      SEMANTIC_TYPE: "FIELD_VALIDATION",
      PROBEABLE: "YES",
      PROBE_TARGET: "password",
      PROBE_TARGET_KIND: "input_password",
      PROBE_TARGET_SELECTOR_SAFE: "input[type='password']:visible",
      INVALID_VALUE_KIND: "too_short",
      EXPECTED_SIGNAL: "NATIVE_VALIDATION_BLOCK",
      REASON: "password_field",
    };
  }

  if (type === "textarea") {
    if (/\/deposits|\/payments|wallet|booking/i.test(route)) {
      return {
        ...base,
        SEMANTIC_TYPE: "MUTATION_ACTION",
        RECOMMENDED_CLASS: "QA_REVERSIBLE_MUTATION",
        REASON: "textarea_on_financial_or_booking_surface",
      };
    }
    return {
      ...base,
      SEMANTIC_TYPE: "FIELD_VALIDATION",
      PROBEABLE: "YES",
      PROBE_TARGET: "textarea",
      PROBE_TARGET_KIND: "textarea",
      PROBE_TARGET_SELECTOR_SAFE: "textarea:visible",
      INVALID_VALUE_KIND: "empty_required",
      EXPECTED_SIGNAL: "NATIVE_VALIDATION_BLOCK",
      REASON: "textarea_field",
    };
  }

  if (type.startsWith("input")) {
    return {
      ...base,
      SEMANTIC_TYPE: "FIELD_VALIDATION",
      PROBEABLE: "YES",
      PROBE_TARGET: "text_input",
      PROBE_TARGET_KIND: "input",
      PROBE_TARGET_SELECTOR_SAFE: "input:visible:not([type='hidden'])",
      INVALID_VALUE_KIND: "empty_required",
      EXPECTED_SIGNAL: "NATIVE_VALIDATION_BLOCK",
      REASON: "generic_input_field",
    };
  }

  if (FILTER_SELECT.test(l)) {
    return {
      ...base,
      SEMANTIC_TYPE: "SEARCH_OR_FILTER_SHELL",
      RECOMMENDED_CLASS: "NOT_APPLICABLE",
      REASON: "filter_label_pattern",
    };
  }

  return {
    ...base,
    SEMANTIC_TYPE: "STRUCTURAL_FORM_ELEMENT",
    RECOMMENDED_CLASS: "NOT_APPLICABLE",
    REASON: "unresolved_default_not_applicable",
  };
}
