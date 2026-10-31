import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
import type {
  GroupBookingConfirmation,
  GroupBookingReview,
  GroupPassengersContext,
  GroupPaymentInstructions,
  GroupPackage,
  GroupLockState,
} from "../types";
import { GROUP_JSON_HEADERS, groupGet, laravelApiPath, type GroupApiResult } from "./group-api-base";

/**
 * Group booking/payment API (mutations + authenticated booking reads).
 * Public search/facets live in group-search-api.ts — do not re-export them here.
 */

type MutatingResult<T> = GroupApiResult<T>;

async function groupMutate<T>(path: string, init: RequestInit): Promise<MutatingResult<T>> {
  const csrf = await ensureLaravelCsrfToken();

  try {
    const response = await fetch(laravelApiPath(path), {
      ...init,
      credentials: "include",
      headers: {
        ...GROUP_JSON_HEADERS,
        ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
        ...init.headers,
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

    return { ok: true, data: payload as T };
  } catch {
    return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
  }
}

export async function fetchGroupPackage(packageId: string) {
  return groupGet<{
    success: boolean;
    package: GroupPackage;
    available: boolean;
    lock_state: GroupLockState;
    progress: Array<{ key: string; label: string; state: string; href?: string | null }>;
  }>(`/groups/package/${encodeURIComponent(packageId)}?format=json`);
}

export async function fetchGroupPassengersContext(packageId: string) {
  return groupGet<{ success: boolean } & GroupPassengersContext>(
    `/groups/${encodeURIComponent(packageId)}/passengers?format=json`,
  );
}

export async function submitGroupPassengers(packageId: string, formData: FormData) {
  return groupMutate<{ success: boolean; redirect_path: string; booking: GroupBookingReview }>(
    `/groups/${encodeURIComponent(packageId)}/passengers`,
    { method: "POST", body: formData },
  );
}

export async function fetchGroupReview(bookingRef: string) {
  return groupGet<{ success: boolean } & GroupBookingReview>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/review?format=json`,
  );
}

export async function confirmGroupReview(bookingRef: string) {
  const csrf = await ensureLaravelCsrfToken();
  const formData = new FormData();
  if (csrf) formData.set("_token", csrf);

  return groupMutate<{ success: boolean; redirect_path: string; booking: GroupPaymentInstructions }>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/review`,
    { method: "POST", body: formData },
  );
}

export async function fetchGroupPayment(bookingRef: string) {
  return groupGet<{ success: boolean } & GroupPaymentInstructions>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/payment?format=json`,
  );
}

export async function submitGroupPayment(bookingRef: string, formData: FormData) {
  return groupMutate<{ success: boolean; redirect_path: string; booking: GroupBookingConfirmation }>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/payment`,
    { method: "POST", body: formData },
  );
}

export async function fetchGroupConfirmation(bookingRef: string) {
  return groupGet<{ success: boolean } & GroupBookingConfirmation>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/confirmation?format=json`,
  );
}

export async function fetchGroupBookingStatus(bookingRef: string) {
  return groupGet<{ success: boolean; booking: GroupBookingReview }>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/status`,
  );
}
