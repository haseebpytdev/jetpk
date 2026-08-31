import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService, SeoJsonLd } from "@/features/public-content";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

export default async function PublicGroupLayout({ children }: { children: ReactNode }) {
  const [session, config] = await Promise.all([
    getPublicSession(),
    PublicConfigService.getConfig(),
  ]);

  return (
    <PublicShell
      session={session}
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
      <SeoJsonLd config={config} />
      {children}
    </PublicShell>
  );
}
