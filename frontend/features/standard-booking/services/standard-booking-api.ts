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
  return standardFetch<StandardPassengersContext>(
    `/booking/passengers?${buildQuery(params)}`,
  );
}

export async function submitStandardPassengers(formData: FormData) {
  return standardFetch<StandardPassengersSubmitResponse>("/booking/passengers?format=json", {
    method: "POST",
    body: formData,
  });
}
