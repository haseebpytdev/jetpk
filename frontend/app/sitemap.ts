import type { MetadataRoute } from "next";
import { appConfig } from "@/lib/config";
import { fetchWithTimeout } from "@/features/public-content/utils/laravel-api";
import { laravelApiPath } from "@/services/flight-search";

export const dynamic = "force-dynamic";

type SitemapRoute = {
  path: string;
  lastmod?: string;
};

async function fetchSitemapRoutes(): Promise<SitemapRoute[]> {
  try {
    const response = await fetchWithTimeout(laravelApiPath("/api/public/content/sitemap-routes"), {
      headers: { Accept: "application/json" },
      next: { revalidate: 3600 },
    });
    if (!response.ok) return [];
    const body = (await response.json()) as { routes?: SitemapRoute[] };
    return body.routes ?? [];
  } catch {
    return [];
  }
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const routes = await fetchSitemapRoutes();
  const base = appConfig.appUrl.replace(/\/$/, "");

  if (routes.length === 0) {
    return [
      { url: `${base}/`, changeFrequency: "daily", priority: 1 },
      { url: `${base}/about-us`, changeFrequency: "weekly", priority: 0.8 },
      { url: `${base}/contact`, changeFrequency: "weekly", priority: 0.8 },
      { url: `${base}/support`, changeFrequency: "weekly", priority: 0.8 },
      { url: `${base}/faq`, changeFrequency: "weekly", priority: 0.7 },
      { url: `${base}/terms`, changeFrequency: "monthly", priority: 0.5 },
      { url: `${base}/privacy`, changeFrequency: "monthly", priority: 0.5 },
    ];
  }

  return routes.map((route) => ({
    url: `${base}/${route.path.replace(/^\//, "")}`,
    lastModified: route.lastmod ? new Date(route.lastmod) : undefined,
  }));
}
