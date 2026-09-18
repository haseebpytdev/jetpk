import { getLaravelApiBase } from "@/lib/read-only/laravel/api-base";

/**
 * Read-only Company Profile branding for dashboard metadata (favicon/title).
 * Uses the same public config authority as the public Next shell — not a second store.
 */
export type DashboardPublicBranding = {
  brand_name?: string;
  favicon_url?: string | null;
  logo_url?: string | null;
};

const FALLBACK_FAVICON = "/favicon.ico";

export function resolveDashboardFaviconUrl(faviconUrl?: string | null): string {
  const trimmed = faviconUrl?.trim() ?? "";
  if (trimmed === "") return FALLBACK_FAVICON;
  if (trimmed.startsWith("http://") || trimmed.startsWith("https://") || trimmed.startsWith("/")) {
    return trimmed;
  }
  if (trimmed.startsWith("storage/") || trimmed.startsWith("client-assets/")) {
    return `/${trimmed}`;
  }
  return FALLBACK_FAVICON;
}

function publicConfigUrl(): string {
  const base = getLaravelApiBase();
  if (base !== "") {
    return `${base}/api/public/content/config`;
  }
  const laravel =
    process.env.LARAVEL_URL?.trim().replace(/\/$/, "") ||
    process.env.NEXT_PUBLIC_LARAVEL_URL?.trim().replace(/\/$/, "") ||
    "";
  if (laravel !== "") {
    return `${laravel}/api/public/content/config`;
  }
  return "/api/public/content/config";
}

export async function getDashboardPublicBranding(): Promise<DashboardPublicBranding | null> {
  try {
    const response = await fetch(publicConfigUrl(), {
      headers: { Accept: "application/json" },
      next: { revalidate: 60, tags: ["public-config", "dashboard-branding"] },
    });
    if (!response.ok) return null;
    const json = (await response.json()) as DashboardPublicBranding;
    return {
      brand_name: typeof json.brand_name === "string" ? json.brand_name : undefined,
      favicon_url: typeof json.favicon_url === "string" ? json.favicon_url : json.favicon_url ?? null,
      logo_url: typeof json.logo_url === "string" ? json.logo_url : json.logo_url ?? null,
    };
  } catch {
    return null;
  }
}
