import { laravelRequest } from "@/lib/api/laravel-action-client";
import type { ApiResult } from "@/lib/api/types";
import type { BookingConfirmation } from "@/features/standard-booking/types/review-payment";

export type GuestBookingDetailPayload = BookingConfirmation & {
  source?: string;
  viewer_mode?: string;
  contact?: { email_masked?: string | null; phone_masked?: string | null };
  capabilities?: {
    can_request_cancellation?: boolean;
    can_upload_payment_proof?: boolean;
    mutation_urls?: {
      request_cancellation?: string | null;
      payment_proof?: string | null;
    };
    blade_fallback_urls?: {
      guest_detail?: string;
      abhipay_start?: string;
      promo_apply?: string;
      promo_remove?: string;
    };
    download_urls?: { invoice?: string | null; ticket?: string | null };
    documents?: Array<{ id: number; title: string; status: string; download_url: string }>;
  };
  cancellation?: {
    state: string;
    label: string;
    message: string;
    request?: { status: string; status_label: string } | null;
  };
  refund?: { state: string; label: string; message: string };
  blade_fallback_url?: string;
  lookup_url?: string;
  passengers?: Array<{
    passenger_type: string;
    display_name: string;
    is_lead_passenger?: boolean;
    passport_number_masked?: string | null;
    national_id_masked?: string | null;
  }>;
};

export type GuestApiResult<T> = ApiResult<T>;

function unwrapGuestPayload<T>(result: ApiResult<Record<string, unknown>>): GuestApiResult<T> {
  if (!result.ok) {
    return result;
  }

  const payload = result.data;
  if (payload && typeof payload === "object" && "ok" in payload && payload.ok === false) {
    const message = (payload as { message?: string }).message ?? "Request failed.";
    return {
      ok: false,
      code: "unknown",
      status: result.status,
      message,
      errors: (payload as { errors?: Record<string, string[]> }).errors,
      data: payload,
    };
  }

  return { ok: true, data: payload as unknown as T, status: result.status };
}

export async function fetchGuestBookingDetail(
  bookingId: string,
  token: string,
): Promise<GuestApiResult<GuestBookingDetailPayload>> {
  const result = await laravelRequest<Record<string, unknown>>(
    `/guest/bookings/${encodeURIComponent(bookingId)}/access/${encodeURIComponent(token)}?format=json`,
    { method: "GET", retryOnNetworkError: true },
  );

  return unwrapGuestPayload<GuestBookingDetailPayload>(result);
}

export async function submitGuestCancellation(
  submitUrl: string,
  payload: { reason?: string; cancellation_type?: string; terms_acknowledged?: boolean },
): Promise<GuestApiResult<{ message: string }>> {
  const formData = new FormData();
  formData.set("cancellation_type", payload.cancellation_type ?? "booking_cancel");
  if (payload.reason) formData.set("reason", payload.reason);
  if (payload.terms_acknowledged) formData.set("terms_acknowledged", "1");

  const result = await laravelRequest<Record<string, unknown>>(submitUrl, {
    method: "POST",
    formData,
  });

  return unwrapGuestPayload<{ message: string }>(result);
}

export async function submitGuestPaymentProof(
  submitUrl: string,
  payload: {
    method: string;
    amount: string;
    payment_reference?: string;
    notes?: string;
  },
): Promise<GuestApiResult<{ message: string }>> {
  const formData = new FormData();
  formData.set("method", payload.method);
  formData.set("amount", payload.amount);
  if (payload.payment_reference) formData.set("payment_reference", payload.payment_reference);
  if (payload.notes) formData.set("notes", payload.notes);

  const result = await laravelRequest<Record<string, unknown>>(submitUrl, {
    method: "POST",
    formData,
  });

  return unwrapGuestPayload<{ message: string }>(result);
}

export function guestApiErrorMessage(result: GuestApiResult<unknown>): string {
  if (result.ok) return "";
  return result.message ?? "Something went wrong. Please try again.";
}
