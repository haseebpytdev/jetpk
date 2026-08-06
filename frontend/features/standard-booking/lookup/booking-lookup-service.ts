import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
import { laravelApiPath } from "@/services/flight-search";
import type { BookingLookupPayload } from "../types/review-payment";
import {
  GENERIC_LOOKUP_FAILURE,
  RATE_LIMIT_MESSAGE,
  TURNSTILE_FAILURE_MESSAGE,
  resolveSafeGuestLookupRedirect,
} from "./guest-redirect";

export type BookingLookupSubmitResult =
  | { ok: true; redirectUrl: string }
  | {
      ok: false;
      status: number;
      message: string;
      fieldErrors?: LaravelValidationErrors;
      turnstileRejected?: boolean;
      rateLimited?: boolean;
      genericFailure?: boolean;
    };

export async function submitBookingLookup(payload: BookingLookupPayload): Promise<BookingLookupSubmitResult> {
  const csrf = await ensureLaravelCsrfToken();
  const formData = new FormData();
  formData.set("booking_reference", payload.booking_reference.trim());
  formData.set("email", payload.email.trim());
  if (payload.phone?.trim()) formData.set("phone", payload.phone.trim());
  if (payload["cf-turnstile-response"]) {
    formData.set("cf-turnstile-response", payload["cf-turnstile-response"]);
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (csrf) headers["X-XSRF-TOKEN"] = csrf;

  try {
    const response = await fetch(laravelApiPath("/lookup-booking"), {
      method: "POST",
      body: formData,
      credentials: "include",
      redirect: "manual",
      headers,
    });

    if (response.status === 429) {
      return { ok: false, status: 429, message: RATE_LIMIT_MESSAGE, rateLimited: true };
    }

    const contentType = response.headers.get("content-type") ?? "";
    if (response.ok && contentType.includes("application/json")) {
      const body = (await response.json()) as { ok?: boolean; redirect_url?: string };
      const redirectUrl = resolveSafeGuestLookupRedirect(body.redirect_url ?? null);
      if (redirectUrl) {
        return { ok: true, redirectUrl };
      }
      return { ok: false, status: response.status, message: GENERIC_LOOKUP_FAILURE, genericFailure: true };
    }

    if (response.type === "opaqueredirect") {
      const redirectUrl = resolveSafeGuestLookupRedirect(response.url);
      if (redirectUrl) {
        return { ok: true, redirectUrl };
      }
      return { ok: false, status: 0, message: GENERIC_LOOKUP_FAILURE, genericFailure: true };
    }

    if (response.status >= 300 && response.status < 400) {
      const redirectUrl = resolveSafeGuestLookupRedirect(response.headers.get("Location"));
      if (redirectUrl) {
        return { ok: true, redirectUrl };
      }
      return { ok: false, status: response.status, message: GENERIC_LOOKUP_FAILURE, genericFailure: true };
    }

    if (response.status === 422) {
      const body = (await response.json()) as {
        message?: string;
        errors?: LaravelValidationErrors;
      };
      const turnstileField = body.errors?.["cf-turnstile-response"]?.[0];
      if (turnstileField) {
        return {
          ok: false,
          status: 422,
          message: TURNSTILE_FAILURE_MESSAGE,
          fieldErrors: body.errors,
          turnstileRejected: true,
        };
      }

      const lookupField = body.errors?.lookup?.[0];
      if (lookupField) {
        return {
          ok: false,
          status: 422,
          message: GENERIC_LOOKUP_FAILURE,
          fieldErrors: body.errors,
          genericFailure: true,
        };
      }

      return {
        ok: false,
        status: 422,
        message: body.message ?? GENERIC_LOOKUP_FAILURE,
        fieldErrors: body.errors,
      };
    }

    return { ok: false, status: response.status, message: GENERIC_LOOKUP_FAILURE, genericFailure: true };
  } catch {
    return { ok: false, status: 0, message: "We could not complete the lookup. Please try again." };
  }
}
