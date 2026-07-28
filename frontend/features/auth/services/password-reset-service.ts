import type { PasswordResetPayload, PasswordResetRequestPayload } from "../types";
import { laravelJsonFetch } from "../utils/laravel-auth-api";
import { sanitizeDashboardUrl } from "../utils/dashboard-allowlist";

export async function requestPasswordReset(payload: PasswordResetRequestPayload) {
  const result = await laravelJsonFetch<{ ok: boolean; message: string }>("/forgot-password", {
    method: "POST",
    formBody: { email: payload.email },
  });

  if (!result.ok) {
    return { ok: false as const, message: result.message, fieldErrors: result.errors, status: result.status };
  }

  return { ok: true as const, message: result.data.message };
}

export async function resetPassword(payload: PasswordResetPayload) {
  const result = await laravelJsonFetch<{ ok: boolean; redirect: string; message?: string }>("/reset-password", {
    method: "POST",
    formBody: {
      token: payload.token,
      email: payload.email,
      password: payload.password,
      password_confirmation: payload.password_confirmation,
    },
  });

  if (!result.ok) {
    return { ok: false as const, message: result.message, fieldErrors: result.errors, status: result.status };
  }

  return {
    ok: true as const,
    redirect: sanitizeDashboardUrl(result.data.redirect, "/login"),
    message: result.data.message,
  };
}
