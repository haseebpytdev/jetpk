import type { Metadata } from "next";
import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import { PublicConfigService, SeoJsonLd, publicSeoToMetadata } from "@/features/public-content";
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

  return publicSeoToMetadata(seo, "/");
}

export default async function HomePage() {
  const session = await getPublicSession();
  const config = await PublicConfigService.getConfig();

  return (
    <PublicShell session={session}>
      <SeoJsonLd config={config} />
      <HomepageContent />
    </PublicShell>
  );
}
