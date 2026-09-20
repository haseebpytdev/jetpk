import type { Metadata } from "next";
import { Suspense } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import { PublicConfigService, SeoJsonLd, publicSeoToMetadata } from "@/features/public-content";
import { resolveFaviconUrl } from "@/lib/branding/resolve-favicon";
import type { PublicSession } from "@/types/session";

const HOMEPAGE_SEO_FALLBACK = {
  title: "JetPakistan | Affordable Flights, Umrah Packages & Tours",
  description:
    "Search and compare domestic and international flights from Pakistan, explore Umrah packages, and plan travel with JetPakistan.",
  robots: "index,follow",
};

/** Soft-nav / ISR: do not await session cookies on the homepage critical path. */
export const revalidate = 60;

const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

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

async function HomeBody() {
  const config = await PublicConfigService.getConfig();
  const branding = config
    ? {
        brand_name: config.brand_name,
        logo_url: config.logo_url,
        header_logo_height: config.header_logo_height,
      }
    : null;

  return (
    <PublicShell session={ANONYMOUS_SESSION} branding={branding} aiEnabled={Boolean(config?.ai_assistant_enabled)}>
      <SeoJsonLd config={config} />
      <HomepageContent />
    </PublicShell>
  );
}

export default function HomePage() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto w-full max-w-jp-container px-jp-xl py-jp-4xl">
          <div className="min-h-[20rem] animate-pulse rounded-jp-card border border-jp-border bg-jp-surface-muted" />
        </div>
      }
    >
      <HomeBody />
    </Suspense>
  );
}
