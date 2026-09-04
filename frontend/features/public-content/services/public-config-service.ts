import { laravelApiPath } from "@/services/flight-search";
import { appConfig } from "@/lib/config";
import type { ContactDetails } from "../types";
import { fetchWithTimeout } from "../utils/laravel-api";

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

export const PublicConfigService = {
  async getConfig(): Promise<PublicConfig | null> {
    try {
      // Public config is not user-specific — avoid cookies()/no-store so layouts
      // that still SSR-fetch config remain cacheable for soft-nav.
      const response = await fetchWithTimeout(publicConfigEndpoint(), {
        headers: { Accept: "application/json" },
        credentials: typeof window !== "undefined" ? "include" : "omit",
        ...(typeof window === "undefined"
          ? { next: { revalidate: 300 } }
          : { cache: "force-cache" as RequestCache }),
      });
      if (!response.ok) return null;
      return (await response.json()) as PublicConfig;
    } catch {
      return null;
    }
  },
};
