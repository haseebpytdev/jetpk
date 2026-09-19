import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService } from "@/features/public-content";
import type { PublicSession } from "@/types/session";

/**
 * Soft-nav / ISR: do not force-dynamic the auth tree.
 * PublicShell upgrades anonymous SSR session from Laravel after hydration
 * (same pattern as `(public)/layout.tsx`).
 */
export const revalidate = 60;

const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export default async function AuthGroupLayout({ children }: { children: ReactNode }) {
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
      {children}
    </PublicShell>
  );
}
