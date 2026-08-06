import { laravelJsonFetch } from "../utils/laravel-auth-api";
import { sanitizeDashboardUrl } from "../utils/dashboard-allowlist";

export type ForcePasswordPayload = {
  password: string;
  password_confirmation: string;
};

export async function submitForcePasswordChange(payload: ForcePasswordPayload) {
  const result = await laravelJsonFetch<{ ok: boolean; redirect: string; message?: string }>(
    "/password/force-change?format=json",
    {
      method: "POST",
      formBody: {
        password: payload.password,
        password_confirmation: payload.password_confirmation,
      },
      retryCsrfOnce: true,
    },
  );

  if (!result.ok) {
    return {
      ok: false as const,
      message: result.message,
      fieldErrors: result.errors,
      status: result.status,
      code: result.code,
    };
  }

  return {
    ok: true as const,
    redirect: sanitizeDashboardUrl(result.data.redirect, "/"),
    message: result.data.message,
  };
}
