import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import type { PublicSession } from "@/types/session";

/**
 * Soft-nav isolation: do not await PublicConfig on the auth layout.
 * Crossing home → /login remounts this segment; a server config fetch was
 * dominating home_login P95 cold outliers. PublicShell upgrades branding +
 * session from Laravel after hydration (same as anonymous public SSR).
 */
export const dynamic = "force-static";
export const revalidate = 60;

const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export default function AuthGroupLayout({ children }: { children: ReactNode }) {
  return (
    <PublicShell session={ANONYMOUS_SESSION} branding={null} aiEnabled={false}>
      {children}
    </PublicShell>
  );
}
