import type { Metadata } from "next";
import { HomepageContent } from "@/features/home";
import { PublicConfigService, publicSeoToMetadata } from "@/features/public-content";
import { resolveFaviconUrl } from "@/lib/branding/resolve-favicon";

const HOMEPAGE_SEO_FALLBACK = {
  title: "JetPakistan | Affordable Flights, Umrah Packages & Tours",
  description:
    "Search and compare domestic and international flights from Pakistan, explore Umrah packages, and plan travel with JetPakistan.",
  robots: "index,follow",
};

/**
 * Soft-nav: homepage lives inside the (public) route group so home→CMS/groups
 * soft navigations reuse PublicShell instead of remounting a separate root shell.
 * URL remains `/` (route groups do not affect the path).
 */
export const revalidate = 60;

export async function generateMetadata(): Promise<Metadata> {
  const config = await PublicConfigService.getConfig();
  const seo = config?.default_seo ?? HOMEPAGE_SEO_FALLBACK;
  const favicon = resolveFaviconUrl(config?.favicon_url);
  const base = publicSeoToMetadata(seo, "/");

  return {
    ...base,
    icons: {
      icon: [{ url: favicon }],
      shortcut: [{ url: favicon }],
    },
  };
}

export default async function HomePage() {
  return <HomepageContent />;
}
