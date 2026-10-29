import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService } from "@/features/public-content";
import type { PublicSession } from "@/types/session";

/**
 * Auth chrome uses the same SSR branding path as public layouts.
 * Valid public config is served from unstable_cache (TTL 3600s), so warm
 * home → /login should HIT cache rather than force a Laravel round trip.
 * Soft-nav P95 must still be gated before shipping (see 01B evidence).
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
    <PublicShell
      session={ANONYMOUS_SESSION}
      branding={branding}
      aiEnabled={Boolean(config?.ai_assistant_enabled)}
    >
      {children}
    </PublicShell>
  );
}
