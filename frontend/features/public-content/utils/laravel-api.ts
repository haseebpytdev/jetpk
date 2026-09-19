import { cache } from "react";
import { absoluteLaravelUrl, laravelApiPath } from "@/services/flight-search";
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
 * Fetch with a client-side abort timeout.
 * Server fetches that use Next `next.revalidate` / tags MUST NOT attach a unique
 * AbortSignal — a fresh AbortController per call defeats Next fetch deduplication
 * across generateMetadata + page + layout in the same request.
 */
export async function fetchWithTimeout(input: string, init?: NextFetchInit): Promise<Response> {
  const isServerCachedFetch = typeof window === "undefined" && Boolean(init?.next);

  if (isServerCachedFetch) {
    return fetch(input, init);
  }

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
 * Request-scoped dedupe for generateMetadata + page (and any other RSC callers)
 * so one soft-nav does not hit Laravel twice for the same managed page key.
 * Preserves next.revalidate: 60 / tag invalidation semantics.
 */
export const fetchManagedPage = cache(async (pageKey: string): Promise<LaravelManagedPageResponse | null> => {
  try {
    const response = await fetchWithTimeout(publicContentFetchUrl(`/api/public/content/pages/${pageKey}`), {
      headers: { Accept: "application/json" },
      next: { revalidate: 60, tags: ["public-seo", `public-seo-${pageKey}`] },
    });
    if (!response.ok) return null;
    return (await response.json()) as LaravelManagedPageResponse;
  } catch {
    return null;
  }
});

export async function fetchSiteContactFromLaravel(): Promise<ContactDetails | null> {
  try {
    const response = await fetchWithTimeout(publicContentFetchUrl("/api/public/content/site-contact"), {
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
    const response = await fetchWithTimeout(publicContentFetchUrl("/api/public/content/support/categories"), {
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
