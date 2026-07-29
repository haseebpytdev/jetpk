import { laravelApiPath } from "@/services/flight-search";
import type { ContactDetails } from "../types";
import { fetchWithTimeout } from "../utils/laravel-api";

export type PublicConfig = {
  brand_name: string;
  domain: string;
  app_url: string;
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
};

export const PublicConfigService = {
  async getConfig(): Promise<PublicConfig | null> {
    try {
      const response = await fetchWithTimeout(laravelApiPath("/api/public/content/config"), {
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
