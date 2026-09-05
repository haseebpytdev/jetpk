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
import {
  buildPassengersFetchQuery,
  hasPassengersHandoffQuery,
  PASSENGERS_JSON_HEADERS,
  shouldFallbackAfterEarlyResult,
  shouldReuseEarlyPrime,
} from "../utils/passengers-fetch-query";

const JSON_HEADERS = PASSENGERS_JSON_HEADERS;

function buildQuery(params: Record<string, string | undefined>): string {
  return buildPassengersFetchQuery(params);
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

function markTravelerBoot(key: string): void {
  if (typeof window === "undefined") return;
  window.__jpTravelerBoot = window.__jpTravelerBoot ?? { marks: {} };
  if (window.__jpTravelerBoot.marks[key] == null) {
    window.__jpTravelerBoot.marks[key] = performance.now();
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
      markTravelerBoot("PASSENGER_FETCH_CALLED");
      markTravelerBoot("PASSENGER_FETCH_REQUEST_START");
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

export function clearStandardPassengersPrime(expectedKey?: string): void {
  if (expectedKey && passengersContextPrime && passengersContextPrime.key !== expectedKey) {
    return;
  }
  passengersContextPrime = null;
  if (typeof window === "undefined") return;
  if (!expectedKey || window.__jpPassengersPrime?.key === expectedKey) {
    window.__jpPassengersPrime = undefined;
  }
}

export async function fetchStandardPassengersContext(
  params: Record<string, string | undefined>,
) {
  const key = buildQuery(params);
  try {
    const result = await primeStandardPassengersContext(params);
    markTravelerBoot("REACT_FETCH_CONSUME");
    return result;
  } finally {
    clearStandardPassengersPrime(key);
  }
}

/** Dedupe in-flight passengers GET so shell priming and page mount share one request. */
let passengersContextPrime:
  | {
      key: string;
      promise: ReturnType<typeof standardFetch<StandardPassengersContext>>;
    }
  | null = null;

type PassengersPrimeResult = ReturnType<typeof standardFetch<StandardPassengersContext>>;

function missingHandoffResult(): Awaited<PassengersPrimeResult> {
  return {
    ok: false,
    status: 404,
    message: "Booking session is missing.",
    data: { status: "missing_session" } as Partial<StandardPassengersContext>,
  };
}

export function primeStandardPassengersContext(params: Record<string, string | undefined>) {
  const key = buildQuery(params);
  markTravelerBoot("PASSENGER_REQUEST_SCHEDULED");
  if (!hasPassengersHandoffQuery(params)) {
    return Promise.resolve(missingHandoffResult());
  }
  if (passengersContextPrime?.key === key) {
    return passengersContextPrime.promise;
  }
  if (typeof window !== "undefined") {
    const early = window.__jpPassengersPrime;
    if (shouldReuseEarlyPrime(early?.key, key) && early?.promise) {
      markTravelerBoot("REACT_FETCH_CONSUME");
      const reused = early.promise.then((result) => {
        if (shouldFallbackAfterEarlyResult(result as { ok?: boolean })) {
          clearStandardPassengersPrime(key);
          return standardFetch<StandardPassengersContext>(`/booking/passengers?${key}`);
        }
        const response = (result as { _response?: Response })._response;
        if (response) {
          markClientHydration("N2_fetch_end_ms");
          capturePassengersTimingHeaders(response);
        }
        return result as Awaited<PassengersPrimeResult>;
      }) as PassengersPrimeResult;
      passengersContextPrime = { key, promise: reused };
      return reused;
    }
  }
  markTravelerBoot("PASSENGER_FETCH_CALLED");
  const promise = standardFetch<StandardPassengersContext>(`/booking/passengers?${key}`);
  passengersContextPrime = { key, promise };
  if (typeof window !== "undefined") {
    window.__jpPassengersPrime = { key, promise, source: "react_prime" };
  }
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
