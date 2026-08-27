import type { LoginPayload, LoginResponse, OtpVerifyPayload, OtpVerifyResponse } from "../types";
import { laravelJsonFetch, mapFieldErrors } from "../utils/laravel-auth-api";
import { sanitizeDashboardUrl } from "../utils/dashboard-allowlist";

const CLIENT_SLUG = "jetpk";

export async function login(payload: LoginPayload): Promise<LoginResponse> {
  const result = await laravelJsonFetch<{ ok: boolean; redirect: string; requires_otp?: boolean }>("/login", {
    method: "POST",
    retryCsrfOnce: true,
    formBody: {
      login: payload.login,
      password: payload.password,
      remember: payload.remember ? "1" : "0",
      client_slug: payload.client_slug ?? CLIENT_SLUG,
      ...(payload.redirect ? { redirect: payload.redirect } : {}),
    },
  });

  if (!result.ok) {
    return {
      ok: false,
      message: result.message,
      fieldErrors: result.errors,
      status: result.status,
      code: result.code,
    };
  }

  return {
    ok: true,
    redirect: sanitizeDashboardUrl(result.data.redirect, "/login/otp"),
    requires_otp: result.data.requires_otp,
  };
}

export async function verifyOtp(payload: OtpVerifyPayload): Promise<OtpVerifyResponse> {
  const result = await laravelJsonFetch<{
    ok: boolean;
    redirect: string;
    dashboard_url?: string;
  }>("/login/otp", {
    method: "POST",
    formBody: {
      otp: payload.otp,
      client_slug: payload.client_slug ?? CLIENT_SLUG,
    },
  });

  if (!result.ok) {
    return {
      ok: false,
      message: result.message,
      fieldErrors: result.errors,
      status: result.status,
      code: result.code,
    };
  }

  const redirect = sanitizeDashboardUrl(result.data.dashboard_url ?? result.data.redirect, "/");

  return {
    ok: true,
    redirect,
    dashboard_url: redirect,
  };
}

export async function resendOtp(clientSlug = CLIENT_SLUG): Promise<
  | { ok: true; resend_available_in: number; message: string }
  | { ok: false; message: string; fieldErrors?: Record<string, string> }
> {
  const result = await laravelJsonFetch<{ ok: boolean; resend_available_in: number; message: string }>(
    "/login/otp/resend",
    {
      method: "POST",
      formBody: { client_slug: clientSlug },
    },
  );

  if (!result.ok) {
    return { ok: false, message: result.message, fieldErrors: mapFieldErrors(result.errors) };
  }

  return {
    ok: true,
    resend_available_in: result.data.resend_available_in,
    message: result.data.message,
  };
}

export async function logout(): Promise<{ ok: true; redirect: string } | { ok: false; message: string }> {
  const result = await laravelJsonFetch<{ ok: boolean; redirect: string }>("/logout", {
    method: "POST",
    formBody: {},
  });

  if (!result.ok) {
    return { ok: false, message: result.message };
  }

  return { ok: true, redirect: sanitizeDashboardUrl(result.data.redirect, "/") };
}

export async function fetchOtpChallenge(): Promise<{
  has_challenge: boolean;
  masked_email?: string | null;
  resend_available_in?: number;
}> {
  const result = await laravelJsonFetch<{
    has_challenge: boolean;
    masked_email?: string | null;
    resend_available_in?: number;
  }>("/api/public/auth/otp-challenge", { method: "GET" });

  if (!result.ok) {
    return { has_challenge: false };
  }

  return result.data;
}
