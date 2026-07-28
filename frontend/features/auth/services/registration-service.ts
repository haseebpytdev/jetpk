import type { AgentRegistrationPayload, CustomerRegistrationPayload } from "../types";
import { laravelJsonFetch } from "../utils/laravel-auth-api";
import { sanitizeDashboardUrl } from "../utils/dashboard-allowlist";

export async function fetchRegistrationSecurityQuestion(): Promise<string | null> {
  const result = await laravelJsonFetch<{ security_question: string }>(
    "/api/public/auth/registration-security-challenge",
    { method: "GET" },
  );

  if (!result.ok) return null;
  return result.data.security_question;
}

export async function registerCustomer(payload: CustomerRegistrationPayload) {
  const result = await laravelJsonFetch<{
    ok: boolean;
    redirect: string;
    requires_email_verification?: boolean;
    message?: string;
  }>("/register", {
    method: "POST",
    formBody: {
      first_name: payload.first_name,
      last_name: payload.last_name,
      email: payload.email,
      mobile_country_code: payload.mobile_country_code,
      mobile: payload.mobile,
      password: payload.password,
      password_confirmation: payload.password_confirmation,
      security_answer: payload.security_answer,
      terms: payload.terms,
    },
  });

  if (!result.ok) {
    return { ok: false as const, message: result.message, fieldErrors: result.errors, status: result.status };
  }

  return {
    ok: true as const,
    redirect: sanitizeDashboardUrl(result.data.redirect, "/verify-email"),
    message: result.data.message,
    requires_email_verification: result.data.requires_email_verification,
  };
}

export async function registerAgent(payload: AgentRegistrationPayload) {
  const result = await laravelJsonFetch<{
    ok: boolean;
    redirect: string;
    pending?: boolean;
    message?: string;
  }>("/agent/register", {
    method: "POST",
    formBody: {
      company_name: payload.company_name,
      city: payload.city,
      business_type: payload.business_type,
      first_name: payload.first_name,
      last_name: payload.last_name ?? "Applicant",
      email: payload.email,
      mobile_country_code: payload.mobile_country_code,
      mobile: payload.mobile,
      country: payload.country ?? "Pakistan",
      office_address: payload.office_address ?? "To be shared during onboarding",
      notes: payload.notes ?? "",
      terms: payload.terms,
    },
  });

  if (!result.ok) {
    return { ok: false as const, message: result.message, fieldErrors: result.errors, status: result.status };
  }

  return {
    ok: true as const,
    redirect: sanitizeDashboardUrl(result.data.redirect, "/agent/register/submitted"),
    pending: result.data.pending,
    message: result.data.message,
  };
}
