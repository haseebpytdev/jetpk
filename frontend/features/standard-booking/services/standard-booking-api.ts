import { laravelApiPath } from "@/services/flight-search";
import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
import {
  attachPassengersServerTiming,
  markClientHydration,
  type PassengersServerTiming,
} from "@/features/flight-results/utils/book-now-timing";
import type {
  StandardPassengersContext,
  StandardPassengersSubmitResponse,
} from "../types";

const JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

function buildQuery(params: Record<string, string | undefined>): string {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== "") {
      search.set(key, value);
    }
  });
  search.set("format", "json");
  return search.toString();
}

function capturePassengersTimingHeaders(response: Response): void {
  const raw = response.headers.get("X-JP-Passengers-Timing");
  if (!raw) return;
  try {
    const parsed = JSON.parse(raw) as PassengersServerTiming;
    attachPassengersServerTiming(parsed);
  } catch {
    /* ignore malformed timing */
  }
}

async function standardFetch<T>(
  path: string,
  init?: RequestInit,
): Promise<
  | { ok: true; data: T }
  | { ok: false; status: number; message: string; errors?: LaravelValidationErrors; data?: Partial<T> }
> {
  const csrf = init?.method && init.method !== "GET" ? await ensureLaravelCsrfToken() : null;
  const bookNowId =
    typeof window !== "undefined" ? window.__jpBookNowTiming?.id ?? null : null;
  const isPassengersGet =
    (!init?.method || init.method === "GET") && path.includes("/booking/passengers");

  try {
    if (isPassengersGet) {
      markClientHydration("N1_fetch_start_ms");
    }
    const response = await fetch(laravelApiPath(path), {
      ...init,
      credentials: "include",
      headers: {
        ...JSON_HEADERS,
        ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
        ...(bookNowId ? { "X-JP-Book-Now-Id": bookNowId } : {}),
        ...init?.headers,
      },
    });

    if (isPassengersGet) {
      markClientHydration("N2_fetch_end_ms");
      capturePassengersTimingHeaders(response);
    }

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

export async function fetchStandardPassengersContext(
  params: Record<string, string | undefined>,
) {
  return primeStandardPassengersContext(params);
}

/** Dedupe in-flight passengers GET so shell priming and page mount share one request. */
let passengersContextPrime:
  | {
      key: string;
      promise: ReturnType<typeof standardFetch<StandardPassengersContext>>;
    }
  | null = null;

export function primeStandardPassengersContext(params: Record<string, string | undefined>) {
  const key = buildQuery(params);
  if (passengersContextPrime?.key === key) {
    return passengersContextPrime.promise;
  }
  const promise = standardFetch<StandardPassengersContext>(`/booking/passengers?${key}`);
  passengersContextPrime = { key, promise };
  void promise.finally(() => {
    // Keep resolved promise for immediate reuse; clear only on mismatch via key check above.
  });
  return promise;
}

export async function submitStandardPassengers(formData: FormData) {
  return standardFetch<StandardPassengersSubmitResponse>("/booking/passengers?format=json", {
    method: "POST",
    body: formData,
  });
}

export async function probeCheckoutGuestEmail(email: string) {
  return standardFetch<{ match: boolean }>("/booking/checkout/guest-email", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email }),
  });
}

export type CheckoutSavedTravelerListItem = {
  id: number;
  title?: string | null;
  first_name: string;
  last_name: string;
  document_number_masked?: string | null;
  document_expiry_status?: string | null;
  is_default?: boolean;
};

export type CheckoutSavedTravelerFill = {
  id: number;
  title?: string | null;
  first_name: string;
  last_name: string;
  gender?: string | null;
  date_of_birth?: string | null;
  nationality?: string | null;
  document_type?: string | null;
  document_number?: string | null;
  document_expiry?: string | null;
  issuing_country?: string | null;
  document_expiry_status?: string | null;
};

export async function fetchCheckoutSavedTravelers() {
  return standardFetch<{
    ok: boolean;
    travelers: CheckoutSavedTravelerListItem[];
    default_traveler_id: number | null;
  }>("/booking/saved-travelers?format=json");
}

export async function fetchCheckoutSavedTraveler(id: number) {
  return standardFetch<{ ok: boolean; traveler: CheckoutSavedTravelerFill }>(
    `/booking/saved-travelers/${id}?format=json`,
  );
}
