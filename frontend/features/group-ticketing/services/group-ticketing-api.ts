import { laravelApiPath } from "@/services/flight-search";
import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
import type {
  GroupBookingConfirmation,
  GroupBookingReview,
  GroupPassengersContext,
  GroupPaymentInstructions,
  GroupResultsPageResponse,
  GroupSearchDataResponse,
  GroupSearchFilters,
  GroupPackage,
  GroupLockState,
  GroupSearchFacetsResponse,
  GroupSearchFacetsLoadState,
  GroupSearchFacetOption,
} from "../types";

const JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

function buildQuery(filters: GroupSearchFilters): string {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value));
    }
  });
  return params.toString();
}

async function groupFetch<T>(
  path: string,
  init?: RequestInit,
): Promise<
  | { ok: true; data: T }
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

    return { ok: true, data: payload as T };
  } catch {
    return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
  }
}

export async function fetchGroupSearchData(filters: GroupSearchFilters) {
  return groupFetch<GroupSearchDataResponse>(`/groups/search/data?${buildQuery(filters)}`);
}

export async function fetchGroupResultsPage(filters: GroupSearchFilters) {
  return groupFetch<GroupResultsPageResponse>(`/groups/search/results?${buildQuery(filters)}`);
}

export async function fetchGroupSearchFacets() {
  return groupFetch<GroupSearchFacetsResponse>("/groups/search/facets");
}

export async function fetchGroupFacets() {
  return groupFetch<{ sectors: string[]; categories: Array<{ slug: string; name: string }> }>("/groups/facets");
}

export type GroupPackagePayload = {
  success: boolean;
  package: GroupPackage;
  available: boolean;
  lock_state: GroupLockState;
  progress: Array<{ key: string; label: string; state: string; href?: string | null }>;
  eligibility?: {
    eligible: boolean;
    reason: string;
    message: string;
    customer_group_booking_enabled: boolean;
  };
};

export async function fetchGroupPackage(packageId: string) {
  return groupFetch<GroupPackagePayload>(
    `/groups/package/${encodeURIComponent(packageId)}?format=json`,
  );
}

/**
 * Server-side package fetch for SSR first paint (read-only, no-store).
 * Avoids the client-only waterfall that leaves "Loading package details…".
 */
export async function fetchGroupPackageServer(packageId: string): Promise<GroupPackagePayload | null> {
  const env = process.env as Record<string, string | undefined>;
  const laravelBase = (env.LARAVEL_URL ?? env.NEXT_PUBLIC_LARAVEL_URL ?? "")
    .trim()
    .replace(/\/$/, "");
  const path = `/groups/package/${encodeURIComponent(packageId)}?format=json`;
  const url = laravelBase !== "" ? `${laravelBase}${path}` : laravelApiPath(path);

  try {
    const response = await fetch(url, {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      cache: "no-store",
    });
    if (!response.ok) return null;
    const payload = (await response.json()) as GroupPackagePayload;
    if (!payload?.package) return null;
    return payload;
  } catch {
    return null;
  }
}

export async function fetchGroupPassengersContext(packageId: string) {
  return groupFetch<{ success: boolean } & GroupPassengersContext>(
    `/groups/${encodeURIComponent(packageId)}/passengers?format=json`,
  );
}

export async function submitGroupPassengers(packageId: string, formData: FormData) {
  return groupFetch<{ success: boolean; redirect_path: string; booking: GroupBookingReview }>(
    `/groups/${encodeURIComponent(packageId)}/passengers`,
    { method: "POST", body: formData },
  );
}

export async function fetchGroupReview(bookingRef: string) {
  return groupFetch<{ success: boolean } & GroupBookingReview>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/review?format=json`,
  );
}

export async function confirmGroupReview(
  bookingRef: string,
  options: { acceptFareChange?: boolean } = {},
) {
  const csrf = await ensureLaravelCsrfToken();
  const formData = new FormData();
  if (csrf) formData.set("_token", csrf);
  if (options.acceptFareChange) {
    formData.set("accept_fare_change", "1");
  }

  return groupFetch<{ success: boolean; redirect_path: string; booking: GroupPaymentInstructions }>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/review`,
    { method: "POST", body: formData },
  );
}

export async function fetchGroupPayment(bookingRef: string) {
  return groupFetch<{ success: boolean } & GroupPaymentInstructions>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/payment?format=json`,
  );
}

export async function submitGroupPayment(bookingRef: string, formData: FormData) {
  return groupFetch<{ success: boolean; redirect_path: string; booking: GroupBookingConfirmation }>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/payment`,
    { method: "POST", body: formData },
  );
}

export async function fetchGroupConfirmation(bookingRef: string) {
  return groupFetch<{ success: boolean } & GroupBookingConfirmation>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/confirmation?format=json`,
  );
}

export async function fetchGroupBookingStatus(bookingRef: string) {
  return groupFetch<{ success: boolean; booking: GroupBookingReview }>(
    `/groups/booking/${encodeURIComponent(bookingRef)}/status`,
  );
}
