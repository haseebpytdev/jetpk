import { fetchLaravelServer } from "@/lib/laravel-server-fetch";

export type DashboardBranding = {
  brandName: string;
  logoUrl: string | null;
};

const FALLBACK_BRAND = "JetPakistan";

export async function getDashboardBranding(): Promise<DashboardBranding> {
  try {
    const response =
      typeof window === "undefined"
        ? await fetchLaravelServer("/api/public/content/config")
        : await fetch("/api/public/content/config", {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            cache: "no-store",
          });

    if (!response.ok) {
      return { brandName: FALLBACK_BRAND, logoUrl: null };
    }

    const payload = (await response.json()) as {
      brand_name?: string;
      logo_url?: string | null;
    };

    const brandName = (payload.brand_name ?? "").trim() || FALLBACK_BRAND;
    const logoUrl = payload.logo_url?.trim() ? payload.logo_url.trim() : null;

    return { brandName, logoUrl };
  } catch {
    return { brandName: FALLBACK_BRAND, logoUrl: null };
  }
}
