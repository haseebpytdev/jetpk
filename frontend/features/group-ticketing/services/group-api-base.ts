import { laravelApiPath } from "@/services/flight-search";

/**
 * Neutral GET-safe group API helpers.
 * Must not import auth/CSRF or mutation helpers — used by public search.
 */

export const GROUP_JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

export type GroupApiValidationErrors = Record<string, string[]>;

export type GroupApiResult<T> =
  | { ok: true; data: T }
  | {
      ok: false;
      status: number;
      message: string;
      errors?: GroupApiValidationErrors;
      data?: Partial<T>;
    };

export function buildGroupQuery(filters: Record<string, unknown>): string {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value));
    }
  });
  return params.toString();
}

export async function groupGet<T>(path: string, init?: RequestInit): Promise<GroupApiResult<T>> {
  try {
    const response = await fetch(laravelApiPath(path), {
      ...init,
      method: init?.method ?? "GET",
      credentials: "include",
      headers: {
        ...GROUP_JSON_HEADERS,
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
        errors: (payload as { errors?: GroupApiValidationErrors } | null)?.errors,
        data: payload as Partial<T>,
      };
    }

    return { ok: true, data: payload as T };
  } catch {
    return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
  }
}

export { laravelApiPath };
