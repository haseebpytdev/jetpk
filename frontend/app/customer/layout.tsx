import type { Metadata } from "next";
import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService } from "@/features/public-content";
import { requireCustomerPortalLayoutAccess } from "@/features/auth/server/customer-portal-access";
import { resolveFaviconUrl } from "@/lib/branding/resolve-favicon";

export const dynamic = "force-dynamic";

export async function generateMetadata(): Promise<Metadata> {
  const config = await PublicConfigService.getConfig();
  const favicon = resolveFaviconUrl(config?.favicon_url);
  return {
    robots: { index: false, follow: false },
    icons: {
      icon: [{ url: favicon }],
      shortcut: [{ url: favicon }],
    },
  };
}

export default async function CustomerLayout({ children }: { children: ReactNode }) {
  const [session, config] = await Promise.all([
    requireCustomerPortalLayoutAccess(),
    PublicConfigService.getConfig(),
  ]);
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
