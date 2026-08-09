import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { AuthCsrfBootstrap } from "@/features/auth/components/AuthCsrfBootstrap";
import { PublicConfigService } from "@/features/public-content";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

export default async function AuthGroupLayout({ children }: { children: ReactNode }) {
  const session = await getPublicSession();
  const config = await PublicConfigService.getConfig();

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
    >
      <AuthCsrfBootstrap />
      {children}
    </PublicShell>
  );
}
