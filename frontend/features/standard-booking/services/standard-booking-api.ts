import { laravelApiPath } from "@/services/flight-search";
import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
import type {
  StandardPassengersContext,
  StandardPassengersSubmitResponse,
} from "../types";
import {
  buildPassengersFetchQuery,
  clearPassengersContextPrime,
  hasPassengersHandoffQuery,
  PASSENGERS_JSON_HEADERS,
  passengersParamsFromHandoffUrl,
  shouldFallbackAfterEarlyResult,
  shouldReuseEarlyPrime,
  writePassengersContextPrime,
} from "../utils/passengers-fetch-query";

const JSON_HEADERS = PASSENGERS_JSON_HEADERS;

function buildQuery(params: Record<string, string | undefined>): string {
  return buildPassengersFetchQuery(params);
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

export function clearStandardPassengersPrime(expectedKey?: string): void {
  if (expectedKey && passengersContextPrime && passengersContextPrime.key !== expectedKey) {
    return;
  }
  passengersContextPrime = null;
  if (typeof window === "undefined") return;
  if (!expectedKey || window.__jpPassengersPrime?.key === expectedKey) {
    window.__jpPassengersPrime = undefined;
  }
  clearPassengersContextPrime(expectedKey);
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

/**
 * Fire passengers JSON before hard-nav assign and persist into sessionStorage so the
 * Traveler document can hydrate without waiting for a cold XHR after unload.
 * Always settles (abort on timeout) so the PHP session lock is not left held across assign.
 */
export async function primePassengersContextBeforeHardNav(
  handoffUrl: string,
  options?: { timeoutMs?: number },
): Promise<boolean> {
  if (typeof window === "undefined" || !handoffUrl) return false;
  const params = passengersParamsFromHandoffUrl(handoffUrl);
  if (!hasPassengersHandoffQuery(params)) return false;
  const key = buildQuery(params);
  const timeoutMs = options?.timeoutMs ?? 2500;
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(laravelApiPath(`/booking/passengers?${key}`), {
      credentials: "include",
      headers: { ...JSON_HEADERS },
      signal: controller.signal,
    });
    const contentType = response.headers.get("content-type") ?? "";
    const payload = contentType.includes("application/json") ? await response.json() : null;
    if (!response.ok || !payload || (payload as { ok?: boolean }).ok !== true) {
      return false;
    }
    writePassengersContextPrime(key, payload);
    passengersContextPrime = {
      key,
      promise: Promise.resolve({ ok: true as const, data: payload as StandardPassengersContext }),
    };
    window.__jpPassengersPrime = {
      key,
      promise: Promise.resolve({ ok: true, data: payload }),
      source: "pre_nav_prime",
    };
    return true;
  } catch {
    return false;
  } finally {
    window.clearTimeout(timer);
  }
}

export async function submitStandardPassengers(formData: FormData) {
  return standardFetch<StandardPassengersSubmitResponse>("/booking/passengers", {
    method: "POST",
    body: formData,
  });
}
