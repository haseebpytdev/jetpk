import type { Metadata } from "next";
import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import { PublicConfigService, SeoJsonLd, publicSeoToMetadata } from "@/features/public-content";
import { resolveFaviconUrl } from "@/lib/branding/resolve-favicon";
import { getPublicSession } from "@/services/session";

const HOMEPAGE_SEO_FALLBACK = {
  title: "JetPakistan | Affordable Flights, Umrah Packages & Tours",
  description:
    "Search and compare domestic and international flights from Pakistan, explore Umrah packages, and plan travel with JetPakistan.",
  robots: "index,follow",
};

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
  const [session, config] = await Promise.all([getPublicSession(), PublicConfigService.getConfig()]);
  const branding = config
    ? {
        brand_name: config.brand_name,
        logo_url: config.logo_url,
        header_logo_height: config.header_logo_height,
      }
    : null;

  return (
    <PublicShell session={session} branding={branding} aiEnabled={Boolean(config?.ai_assistant_enabled)}>
      <SeoJsonLd config={config} />
      <HomepageContent />
    </PublicShell>
  );
}
