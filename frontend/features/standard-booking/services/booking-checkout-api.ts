import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
import { laravelApiPath } from "@/services/flight-search";
import type {
  BookingReviewContext,
  CardPaymentStartResponse,
  CheckoutState,
  InvoicePayload,
  PaymentStatusResponse,
  ReviewSubmitResponse,
} from "../types/review-payment";

const JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

async function checkoutFetch<T>(
  path: string,
  init?: RequestInit,
): Promise<
  | { ok: true; data: T; status: number }
  | { ok: false; status: number; message: string; errors?: LaravelValidationErrors; data?: Partial<T> }
> {
  const csrf = init?.method && init.method !== "GET" ? await ensureLaravelCsrfToken() : null;

  try {
    const response = await fetch(laravelApiPath(path), {
      ...init,
      credentials: "include",
      headers: {
        ...JSON_HEADERS,
        ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
        ...init?.headers,
      },
    });

    const contentType = response.headers.get("content-type") ?? "";
    const payload = contentType.includes("application/json") ? await response.json() : null;

    if (!response.ok) {
      return {
        ok: false,
        status: response.status,
        message: (payload as { message?: string } | null)?.message ?? "Request failed.",
        errors: (payload as { errors?: LaravelValidationErrors } | null)?.errors,
        data: payload as Partial<T>,
      };
    }

    return { ok: true, data: payload as T, status: response.status };
  } catch {
    return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
  }
}

export async function fetchBookingReview() {
  return checkoutFetch<BookingReviewContext>("/booking/review?format=json");
}

export async function submitBookingReview(bookingMethod: string) {
  const formData = new FormData();
  formData.set("booking_method", bookingMethod);

  return checkoutFetch<ReviewSubmitResponse>("/booking/review?format=json", {
    method: "POST",
    body: formData,
  });
}

export async function fetchCheckoutState() {
  return checkoutFetch<CheckoutState>("/booking/checkout-state?format=json");
}

export async function fetchPaymentStatus(reference?: string) {
  const query = new URLSearchParams({ format: "json" });
  if (reference) query.set("reference", reference);
  return checkoutFetch<PaymentStatusResponse>(`/booking/payment/status?${query.toString()}`);
}

export async function startCardPayment(startEndpoint: string) {
  return checkoutFetch<CardPaymentStartResponse>(`${startEndpoint}?format=json`, { method: "POST" });
}

export async function fetchInvoice() {
  return checkoutFetch<InvoicePayload>("/booking/invoice?format=json");
}

export async function acceptUpdatedFare(bookingId: number) {
  return checkoutFetch<{ ok: boolean; message?: string }>(`/booking/${bookingId}/accept-updated-fare?format=json`, {
    method: "POST",
  });
}

export async function declineUpdatedFare(bookingId: number) {
  return checkoutFetch<{ ok: boolean; message?: string }>(`/booking/${bookingId}/decline-updated-fare?format=json`, {
    method: "POST",
  });
}
