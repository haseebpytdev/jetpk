import { laravelApiPath } from "@/services/flight-search";
import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import type { LaravelValidationErrors } from "@/features/auth/utils/laravel-auth-api";
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

export async function fetchStandardPassengersContext(
  params: Record<string, string | undefined>,
) {
  return standardFetch<StandardPassengersContext>(
    `/booking/passengers?${buildQuery(params)}`,
  );
}

export async function submitStandardPassengers(formData: FormData) {
  return standardFetch<StandardPassengersSubmitResponse>("/booking/passengers", {
    method: "POST",
    body: formData,
  });
}
