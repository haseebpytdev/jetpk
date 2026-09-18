import type { Metadata } from "next";
import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService } from "@/features/public-content";
import { getPublicSession } from "@/services/session";
import { resolveFaviconUrl } from "@/lib/branding/resolve-favicon";

export async function generateMetadata(): Promise<Metadata> {
  const config = await PublicConfigService.getConfig();
  const favicon = resolveFaviconUrl(config?.favicon_url);
  return {
    icons: {
      icon: [{ url: favicon }],
      shortcut: [{ url: favicon }],
    },
  };
}

export default async function FlightsLayout({ children }: { children: ReactNode }) {
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
      {children}
    </PublicShell>
  );
}
