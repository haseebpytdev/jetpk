import { cache } from "react";
import { unstable_cache } from "next/cache";
import { laravelApiPath } from "@/services/flight-search";
import { appConfig } from "@/lib/config";
import {
  PUBLIC_CACHE_TAGS,
  PUBLIC_CONTENT_CACHE_TTL_SECONDS,
} from "@/lib/public-cache-tags";
import { recordLaravelPublicConfigCall } from "@/lib/public-content-cache-metrics";
import type { ContactDetails } from "../types";

export type PublicConfig = {
  brand_name: string;
  domain: string;
  app_url: string;
  logo_url?: string | null;
  favicon_url?: string | null;
  header_logo_height?: number;
  contact: ContactDetails;
  legal_paths: {
    terms: string;
    privacy: string;
  };
  support_path: string;
  contact_path: string;
  booking_lookup_path: string;
  groups_path: string;
  social_links: Array<{ label: string; href: string }>;
  default_seo: {
    title: string;
    description: string;
    robots: string;
  };
  site_verification?: {
    google?: string | null;
    bing?: string | null;
  };
  source: "laravel";
  commerce_gates?: {
    guest_booking_enabled: boolean;
    card_payment_enabled: boolean;
    customer_group_booking_enabled?: boolean;
    customer_registration_enabled?: boolean;
  };
  ai_assistant_enabled?: boolean;
  ai_assistant_mode?: string;
};

function publicConfigEndpoint(): string {
  if (typeof window !== "undefined") {
    return laravelApiPath("/api/public/content/config");
  }

  const laravelBase = (
    process.env.LARAVEL_URL ??
    process.env.NEXT_PUBLIC_LARAVEL_URL ??
    ""
  )
    .trim()
    .replace(/\/$/, "");
  if (laravelBase !== "") {
    return `${laravelBase}/api/public/content/config`;
  }

  const appBase = appConfig.appUrl.replace(/\/$/, "");
  return `${appBase}/laravel/api/public/content/config`;
}

/**
 * Raw Laravel public-config fetch. Always no-store + bounded timeout.
 * Cross-request persistence is owned by unstable_cache.
 */
async function fetchPublicConfigRaw(): Promise<PublicConfig | null> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 3_000);
  try {
    const response = await fetch(publicConfigEndpoint(), {
      headers: { Accept: "application/json" },
      credentials: typeof window !== "undefined" ? "include" : "omit",
      signal: controller.signal,
      cache: "no-store",
    });
    if (!response.ok) return null;
    return (await response.json()) as PublicConfig;
  } catch {
    return null;
  } finally {
    clearTimeout(timeout);
  }
}

async function getCachedPublicConfig(): Promise<PublicConfig | null> {
  let cacheMiss = false;
  const data = await unstable_cache(
    async () => {
      cacheMiss = true;
      return fetchPublicConfigRaw();
    },
    ["jp-public-config"],
    {
      revalidate: PUBLIC_CONTENT_CACHE_TTL_SECONDS,
      tags: [PUBLIC_CACHE_TAGS.content, PUBLIC_CACHE_TAGS.config],
    },
  )();

  recordLaravelPublicConfigCall(cacheMiss ? "MISS" : "HIT");
  return data;
}

async function getConfigImpl(options?: { preview?: boolean }): Promise<PublicConfig | null> {
  // Browser / draft-preview: never use persistent public cache.
  if (typeof window !== "undefined" || options?.preview) {
    recordLaravelPublicConfigCall("BYPASS");
    return fetchPublicConfigRaw();
  }

  return getCachedPublicConfig();
}

export const PublicConfigService = {
  /**
   * React cache() → unstable_cache → raw Laravel fetch.
   * One config resolution per RSC request; persistent across requests for published public.
   */
  getConfig: cache(getConfigImpl),
};
