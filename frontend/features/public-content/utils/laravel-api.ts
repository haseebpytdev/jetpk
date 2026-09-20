import { cache } from "react";
import { unstable_cache } from "next/cache";
import { absoluteLaravelUrl, laravelApiPath } from "@/services/flight-search";
import { PUBLIC_CACHE_TAGS } from "@/lib/public-cache-tags";
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
 * Timeout without AbortSignal on the fetch — AbortSignal busts Next Data Cache keys
 * and was regressing cold soft-nav to CMS pages (home_privacy P95 multi-second).
 */
async function fetchJsonNoAbortSignal<T>(
  url: string,
  init: NextFetchInit,
): Promise<T | null> {
  try {
    const response = await Promise.race([
      fetch(url, init),
      new Promise<Response>((_, reject) => {
        setTimeout(() => reject(new Error("laravel_fetch_timeout")), LARAVEL_FETCH_TIMEOUT_MS);
      }),
    ]);
    if (!response.ok) return null;
    return (await response.json()) as T;
  } catch {
    return null;
  }
}

async function loadManagedPageFromLaravel(pageKey: string): Promise<LaravelManagedPageResponse | null> {
  return fetchJsonNoAbortSignal<LaravelManagedPageResponse>(
    publicContentFetchUrl(`/api/public/content/pages/${pageKey}`),
    {
      headers: { Accept: "application/json" },
      next: {
        revalidate: 300,
        tags: [PUBLIC_CACHE_TAGS.seo, PUBLIC_CACHE_TAGS.seoPage(pageKey), PUBLIC_CACHE_TAGS.cms],
      },
    },
  );
}

/**
 * Cross-request persistent cache for published public CMS pages.
 * React `cache()` only dedupes within one RSC request; soft-nav needs Data Cache.
 * Preview/draft must call a no-store path separately (not this helper).
 */
function getCachedManagedPage(pageKey: string) {
  return unstable_cache(
    async () => loadManagedPageFromLaravel(pageKey),
    ["jp-public-managed-page", pageKey],
    {
      revalidate: 300,
      tags: [PUBLIC_CACHE_TAGS.seo, PUBLIC_CACHE_TAGS.seoPage(pageKey), PUBLIC_CACHE_TAGS.cms],
    },
  )();
}

/**
 * Request-scoped dedupe for generateMetadata + page, backed by unstable_cache
 * for cross-request soft-nav. ISR tags align with Laravel PublicCacheTags.
 */
export const fetchManagedPage = cache(async (pageKey: string): Promise<LaravelManagedPageResponse | null> => {
  return getCachedManagedPage(pageKey);
});

export const fetchSiteContactFromLaravel = cache(async (): Promise<ContactDetails | null> => {
  const body = await unstable_cache(
    async () =>
      fetchJsonNoAbortSignal<{ contact?: ContactDetails }>(
        publicContentFetchUrl("/api/public/content/site-contact"),
        {
          headers: { Accept: "application/json" },
          next: { revalidate: 300, tags: [PUBLIC_CACHE_TAGS.seo, "public-site-contact"] },
        },
      ),
    ["jp-public-site-contact"],
    { revalidate: 300, tags: [PUBLIC_CACHE_TAGS.seo, "public-site-contact"] },
  )();
  return body?.contact ?? null;
});

export const fetchSupportCategories = cache(async (): Promise<SupportTicketCategoryOption[]> => {
  const body = await unstable_cache(
    async () =>
      fetchJsonNoAbortSignal<{ categories?: SupportTicketCategoryOption[] }>(
        publicContentFetchUrl("/api/public/content/support/categories"),
        {
          headers: { Accept: "application/json" },
          next: { revalidate: 3600, tags: [PUBLIC_CACHE_TAGS.seo, "public-support-categories"] },
        },
      ),
    ["jp-public-support-categories"],
    { revalidate: 3600, tags: [PUBLIC_CACHE_TAGS.seo, "public-support-categories"] },
  )();
  return body?.categories ?? [];
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
