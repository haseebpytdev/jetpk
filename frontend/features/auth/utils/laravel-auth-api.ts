import { laravelApiPath } from "@/services/flight-search";

export type LaravelValidationErrors = Record<string, string[]>;

const JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

export async function ensureLaravelCsrfToken(): Promise<string | null> {
  const existing = readCookie("XSRF-TOKEN");
  if (existing) return existing;

  try {
    const response = await fetch(laravelApiPath("/api/public/content/csrf-token"), {
      credentials: "include",
      headers: JSON_HEADERS,
    });
    if (!response.ok) return null;
    const body = (await response.json()) as { csrf_token?: string };
    return body.csrf_token ?? readCookie("XSRF-TOKEN");
  } catch {
    return null;
  }
}

export async function laravelJsonFetch<T>(
  path: string,
  init?: RequestInit & { formBody?: Record<string, string | undefined> },
): Promise<{ ok: true; data: T } | { ok: false; status: number; message: string; errors?: LaravelValidationErrors }> {
  const csrf = init?.method && init.method !== "GET" ? await ensureLaravelCsrfToken() : null;

  let body: BodyInit | undefined = init?.body ?? undefined;
  if (init?.formBody) {
    const formData = new FormData();
    Object.entries(init.formBody).forEach(([key, value]) => {
      if (value !== undefined) formData.append(key, value);
    });
    body = formData;
  }

  try {
    const response = await fetch(laravelApiPath(path), {
      ...init,
      credentials: "include",
      headers: {
        ...JSON_HEADERS,
        ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
        ...init?.headers,
      },
      body,
    });

    const contentType = response.headers.get("content-type") ?? "";
    const isJson = contentType.includes("application/json");
    const payload = isJson ? await response.json() : null;

    if (!response.ok) {
      const errors = (payload as { errors?: LaravelValidationErrors } | null)?.errors;
      const message =
        (payload as { message?: string } | null)?.message ??
        (response.status === 429
          ? "Too many attempts. Please wait a moment and try again."
          : "Request failed. Please try again.");

      return { ok: false, status: response.status, message, errors };
    }

    return { ok: true, data: payload as T };
  } catch {
    return { ok: false, status: 0, message: "Network error. Check your connection and try again." };
  }
}

export function mapFieldErrors(errors?: LaravelValidationErrors): Record<string, string> {
  if (!errors) return {};
  const mapped: Record<string, string> = {};
  Object.entries(errors).forEach(([key, messages]) => {
    mapped[key] = messages[0] ?? "Invalid value";
  });
  return mapped;
}

export function buildCookieHeader(cookiePairs: Array<{ name: string; value: string }>): string {
  return cookiePairs.map((cookie) => `${cookie.name}=${cookie.value}`).join("; ");
}
