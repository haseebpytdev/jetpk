import type { ReactNode } from "react";
import { Suspense } from "react";
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

async function AuthLayoutBody({ children }: { children: ReactNode }) {
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

/** Soft-nav: Suspense config-bound shell so home→login URL can commit while config resolves. */
export default function AuthGroupLayout({ children }: { children: ReactNode }) {
  return (
    <Suspense
      fallback={
        <PublicShell session={ANONYMOUS_SESSION} branding={null} aiEnabled={false}>
          {children}
        </PublicShell>
      }
    >
      <AuthLayoutBody>{children}</AuthLayoutBody>
    </Suspense>
  );
}
