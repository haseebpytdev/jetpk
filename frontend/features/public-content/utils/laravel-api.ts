import { laravelApiPath } from "@/services/flight-search";
import type {
  ContactDetails,
  ContactFormPayload,
  ContactFormResponse,
  LaravelManagedPageResponse,
  SupportTicketCategoryOption,
} from "../types";
import { SITE_CONTACT_FIXTURE } from "../fixtures/site-contact";
import { allowContentFixtures } from "./content-policy";

export type LaravelValidationErrors = Record<string, string[]>;

const LARAVEL_FETCH_TIMEOUT_MS = 3_000;

type FetchInit = RequestInit & {
  next?: { revalidate?: number | false; tags?: string[] };
};

function isCacheableFetch(init?: FetchInit): boolean {
  if (!init) return false;
  if (init.cache === "force-cache") return true;
  if (init.cache === "no-store") return false;
  const revalidate = init.next?.revalidate;
  return typeof revalidate === "number" && revalidate > 0;
}

/**
 * Timed fetch for Laravel public APIs.
 * Cacheable ISR fetches must NOT receive AbortSignal — Next treats signal as
 * opting out of fetch memoization/data cache, which kept About/FAQ/home ƒ-dynamic
 * and made soft-nav re-hit Laravel every click.
 * Also do NOT Promise.race-reject cacheable fetches at 3s: that produced
 * Privacy/Terms/Contact soft-nav P95≈2.6–3.4s on ISR miss (regen aborted to null).
 */
export async function fetchWithTimeout(input: string, init?: FetchInit): Promise<Response> {
  if (isCacheableFetch(init)) {
    return await fetch(input, init);
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), LARAVEL_FETCH_TIMEOUT_MS);

  try {
    return await fetch(input, {
      ...init,
      signal: init?.signal ?? controller.signal,
    });
  } finally {
      clearTimeout(timeout);
  }
}

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

export async function ensureLaravelCsrfToken(): Promise<string | null> {
  const existing = readCookie("XSRF-TOKEN");
  if (existing) return existing;

  try {
    const response = await fetchWithTimeout(laravelApiPath("/api/public/content/csrf-token"), {
      credentials: "include",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    if (!response.ok) return null;
    return readCookie("XSRF-TOKEN");
  } catch {
    return null;
  }
}

function managedPageRequestUrl(apiPath: string): string {
  // Browser: same-origin /laravel proxy. Server: prefer absolute LARAVEL_URL when set
  // (matches homepage SSR, enables production-like local CMS stubs). Otherwise keep
  // relative /laravel so OLS can proxy when LARAVEL_URL is unset.
  //
  // Use dynamic env access so Next does not bake an empty LARAVEL_URL at build time
  // (local production-like harness sets LARAVEL_URL only at `next start`).
  if (typeof window !== "undefined") {
    return laravelApiPath(apiPath);
  }
  const env = process.env as Record<string, string | undefined>;
  const laravelBase = (env["LARAVEL_URL"] ?? env["NEXT_PUBLIC_LARAVEL_URL"] ?? "")
    .trim()
    .replace(/\/$/, "");
  if (laravelBase !== "") {
    return `${laravelBase}${apiPath.startsWith("/") ? apiPath : `/${apiPath}`}`;
  }
  return laravelApiPath(apiPath);
}

export async function fetchManagedPage(
  pageKey: string,
  options?: { preview?: boolean; headers?: Record<string, string>; previewToken?: string | null },
): Promise<LaravelManagedPageResponse | null> {
  try {
    const params = new URLSearchParams();
    if (options?.preview) {
      params.set("jp_preview", "1");
    }
    const token = options?.previewToken?.trim();
    if (token) {
      params.set("jp_preview_token", token);
    }
    const query = params.toString();
    const path = `/api/public/content/pages/${pageKey}${query ? `?${query}` : ""}`;
    const isPreview = Boolean(options?.preview || token);
    const response = await fetchWithTimeout(managedPageRequestUrl(path), {
      headers: {
        Accept: "application/json",
        ...(options?.headers ?? {}),
      },
      // Published CMS pages: ISR window. Preview must never reuse cached publish payload.
      ...(isPreview ? { cache: "no-store" as const } : { next: { revalidate: 300 } }),
    });
    if (!response.ok) return null;
    return (await response.json()) as LaravelManagedPageResponse;
  } catch {
    return null;
  }
}

export async function fetchSiteContactFromLaravel(): Promise<ContactDetails | null> {
  try {
    const response = await fetchWithTimeout(laravelApiPath("/api/public/content/site-contact"), {
      headers: { Accept: "application/json" },
      next: { revalidate: 300 },
    });
    if (!response.ok) return null;
    const body = (await response.json()) as { contact?: ContactDetails };
    return body.contact ?? null;
  } catch {
    return null;
  }
}

export async function fetchSupportCategories(): Promise<SupportTicketCategoryOption[]> {
  try {
    const response = await fetchWithTimeout(laravelApiPath("/api/public/content/support/categories"), {
      headers: { Accept: "application/json" },
      next: { revalidate: 3600 },
    });
    if (!response.ok) return [];
    const body = (await response.json()) as { categories?: SupportTicketCategoryOption[] };
    return body.categories ?? [];
  } catch {
    return [];
  }
}

export async function submitSupportOrContactForm(payload: ContactFormPayload): Promise<ContactFormResponse> {
  const csrf = await ensureLaravelCsrfToken();
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== "") {
      formData.append(key, value);
    }
  });

  try {
    const response = await fetch(laravelApiPath("/support"), {
      method: "POST",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
      },
      body: formData,
    });

    if (response.status === 422) {
      const body = (await response.json()) as { message?: string; errors?: LaravelValidationErrors };
      return {
        ok: false,
        message: body.message ?? "Please fix the highlighted fields.",
        fieldErrors: body.errors,
        status: 422,
      };
    }

    if (!response.ok) {
      return {
        ok: false,
        message: "We could not submit your request. Please try again.",
        status: response.status,
      };
    }

    const body = (await response.json()) as { ticket_reference?: string };
    if (!body.ticket_reference) {
      return { ok: false, message: "Unexpected response from support service.", status: response.status };
    }

    return { ok: true, ticket_reference: body.ticket_reference };
  } catch {
    return { ok: false, message: "Network error. Check your connection and try again." };
  }
}

export function mergeContactDetails(primary: ContactDetails | null | undefined): ContactDetails {
  if (!primary) {
    return allowContentFixtures() ? SITE_CONTACT_FIXTURE : {
      phone: "",
      phone_e164: "",
      email: "",
      whatsapp: "",
      website: "",
      office: "",
      hours: "",
      company_legal_name: "",
    };
  }

  if (!allowContentFixtures()) {
    return primary;
  }

  return {
    ...SITE_CONTACT_FIXTURE,
    ...Object.fromEntries(Object.entries(primary).filter(([, value]) => value !== "")),
  } as ContactDetails;
}
