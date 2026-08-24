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
  };
};

function publicConfigEndpoint(): string {
  if (typeof window !== "undefined") {
    return laravelApiPath("/api/public/content/config");
  }

  const appBase = appConfig.appUrl.replace(/\/$/, "");
  return `${appBase}/laravel/api/public/content/config`;
}

export const PublicConfigService = {
  async getConfig(): Promise<PublicConfig | null> {
    try {
      const response = await fetchWithTimeout(publicConfigEndpoint(), {
        headers: { Accept: "application/json" },
        next: { revalidate: 300 },
      });
      if (!response.ok) return null;
      return (await response.json()) as PublicConfig;
    } catch {
      return null;
    }
  },
};
