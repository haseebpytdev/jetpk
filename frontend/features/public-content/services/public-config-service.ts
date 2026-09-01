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
      const headers: Record<string, string> = { Accept: "application/json" };
      let cookieHeader: string | undefined;

      if (typeof window === "undefined") {
        try {
          const { cookies } = await import("next/headers");
          const store = await cookies();
          cookieHeader = store
            .getAll()
            .map((c) => `${c.name}=${c.value}`)
            .join("; ");
          if (cookieHeader) {
            headers.Cookie = cookieHeader;
          }
        } catch {
          /* ignore */
        }
      }

      const response = await fetchWithTimeout(publicConfigEndpoint(), {
        headers,
        credentials: "include",
        cache: "no-store",
      });
      if (!response.ok) return null;
      return (await response.json()) as PublicConfig;
    } catch {
      return null;
    }
  },
};
