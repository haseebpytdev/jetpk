import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import type { Metadata } from "next";
import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { requireCustomerPortalLayoutAccess } from "@/features/auth/server/customer-portal-access";
import { PortalAppFooter } from "@/features/portal";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  robots: { index: false, follow: false },
};

export default async function CustomerLayout({ children }: { children: ReactNode }) {
  const [session, config] = await Promise.all([
    requireCustomerPortalLayoutAccess(),
    PublicConfigService.getConfig(),
  ]);

  return (
    <PublicShell
      session={session}
      hideFooter
      branding={
        config
          ? {
              brand_name: config.brand_name,
              logo_url: config.logo_url,
              header_logo_height: config.header_logo_height,
            }
          : null
      }
      aiEnabled={Boolean(config?.ai_assistant_enabled)}
    >
      {children}
      <PortalAppFooter />
    </PublicShell>
  );
}
