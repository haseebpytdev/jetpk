import { cache } from "react";
import { unstable_cache } from "next/cache";
import { absoluteLaravelUrl, laravelApiPath } from "@/services/flight-search";
import {
  PUBLIC_CACHE_TAGS,
  PUBLIC_CONTENT_CACHE_TTL_SECONDS,
} from "@/lib/public-cache-tags";
import { recordLaravelPublicPageCall } from "@/lib/public-content-cache-metrics";
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

type NextFetchInit = RequestInit & { next?: { revalidate?: number | false; tags?: string[] } };

export type FetchManagedPageOptions = {
  /** Draft/preview must bypass persistent public cache. */
  preview?: boolean;
  previewToken?: string | null;
};

/**
 * Server components must call Laravel directly (runtime LARAVEL_URL) because
 * Next rewrites are baked at build time and can target the wrong loopback host.
 */
export function publicContentFetchUrl(apiPath: string): string {
  const normalized = apiPath.startsWith("/") ? apiPath : `/${apiPath}`;
  if (typeof window === "undefined") {
    return absoluteLaravelUrl(normalized);
  }

  return laravelApiPath(normalized);
}

/**
 * Fetch with abort timeout.
 *
 * Server + `next.revalidate` callers that are NOT wrapped in React `cache()` must
 * not invent a unique AbortSignal per call (breaks Next fetch dedupe). Prefer
 * putting the timeout *inside* a `cache()` wrapper (one signal per request key).
 *
 * Plain server cached fetch without an outer cache() still uses a timeout via
 * AbortSignal when the caller does not pass `signal` — but managed-page/config
 * go through cache() implementations that own the timer.
 */
export async function fetchWithTimeout(input: string, init?: NextFetchInit): Promise<Response> {
  if (init?.signal) {
    return fetch(input, init);
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), LARAVEL_FETCH_TIMEOUT_MS);

  try {
    return await fetch(input, {
      ...init,
      signal: controller.signal,
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
    const body = (await response.json()) as { csrf_token?: string };
    return body.csrf_token ?? readCookie("XSRF-TOKEN");
  } catch {
    return null;
  }
}

/**
 * Raw Laravel managed-page fetch. Always no-store + bounded timeout.
 * Cross-request persistence is owned by unstable_cache, not fetch next.revalidate.
 */
async function fetchManagedPageRaw(
  pageKey: string,
  options?: FetchManagedPageOptions,
): Promise<LaravelManagedPageResponse | null> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), LARAVEL_FETCH_TIMEOUT_MS);
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
    const url = `${publicContentFetchUrl(`/api/public/content/pages/${pageKey}`)}${query ? `?${query}` : ""}`;

    const response = await fetch(url, {
      headers: { Accept: "application/json" },
      signal: controller.signal,
      cache: "no-store",
    });
    if (!response.ok) return null;
    return (await response.json()) as LaravelManagedPageResponse;
  } catch {
    return null;
  } finally {
    clearTimeout(timeout);
  }
}

/**
 * React cache() → unstable_cache → raw Laravel fetch.
 * Request-level dedupe for generateMetadata + page; persistent cache for published public.
 * Draft/preview bypasses unstable_cache.
 */
export const fetchManagedPage = cache(
  async (
    pageKey: string,
    options?: FetchManagedPageOptions,
  ): Promise<LaravelManagedPageResponse | null> => {
    const key = pageKey.trim();
    if (key === "") {
      return null;
    }

    if (options?.preview || options?.previewToken) {
      recordLaravelPublicPageCall(key, "BYPASS");
      return fetchManagedPageRaw(key, options);
    }

    let cacheMiss = false;
    const data = await unstable_cache(
      async () => {
        cacheMiss = true;
        return fetchManagedPageRaw(key);
      },
      ["jp-public-page", key],
      {
        revalidate: PUBLIC_CONTENT_CACHE_TTL_SECONDS,
        tags: [PUBLIC_CACHE_TAGS.content, PUBLIC_CACHE_TAGS.page(key)],
      },
    )();

    recordLaravelPublicPageCall(key, cacheMiss ? "MISS" : "HIT");
    return data;
  },
);

export const fetchSiteContactFromLaravel = cache(async (): Promise<ContactDetails | null> => {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), LARAVEL_FETCH_TIMEOUT_MS);
  try {
    const response = await fetch(publicContentFetchUrl("/api/public/content/site-contact"), {
      headers: { Accept: "application/json" },
      signal: controller.signal,
      next: { revalidate: 300, tags: ["public-seo", "public-site-contact"] },
    });
    if (!response.ok) return null;
    const body = (await response.json()) as { contact?: ContactDetails };
    return body.contact ?? null;
  } catch {
    return null;
  } finally {
    clearTimeout(timeout);
  }
});

export const fetchSupportCategories = cache(async (): Promise<SupportTicketCategoryOption[]> => {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), LARAVEL_FETCH_TIMEOUT_MS);
  try {
    const response = await fetch(publicContentFetchUrl("/api/public/content/support/categories"), {
      headers: { Accept: "application/json" },
      signal: controller.signal,
      next: { revalidate: 3600, tags: ["public-seo", "public-support-categories"] },
    });
    if (!response.ok) return [];
    const body = (await response.json()) as { categories?: SupportTicketCategoryOption[] };
    return body.categories ?? [];
  } catch {
    return [];
  } finally {
    clearTimeout(timeout);
  }
});

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
  const normalize = (contact: ContactDetails): ContactDetails => ({
    ...contact,
    website: normalizePublicWebsite(contact.website ?? ""),
  });

  if (!primary) {
    return allowContentFixtures() ? normalize(SITE_CONTACT_FIXTURE) : {
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
    return normalize(primary);
  }

  return normalize({
    ...SITE_CONTACT_FIXTURE,
    ...Object.fromEntries(Object.entries(primary).filter(([, value]) => value !== "")),
  } as ContactDetails);
}

function normalizePublicWebsite(website: string): string {
  const trimmed = website.trim();
  if (trimmed === "") {
    return "";
  }

  return trimmed.replace(/^https?:\/\/(www\.)?jetpakistan\.com\/?$/i, "https://jetpakistan.pk");
}
