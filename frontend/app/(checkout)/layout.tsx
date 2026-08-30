import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { getPublicSession } from "@/services/session";

/**
 * Checkout soft-nav layout (Book Now → Traveler).
 *
 * Intentionally NOT under `(public)` force-dynamic layout that also awaits
 * PublicConfig via the public origin. That cross-layout hop was the dominant
 * T7→T8 stall surface (session/config contention up to ~30s).
 *
 * Mirrors `app/flights/layout.tsx`: session-only SSR with AbortSignal-bounded
 * fetch inside getPublicSession. No Promise.race orphan fallback.
 */
export const dynamic = "force-dynamic";

export default async function CheckoutLayout({ children }: { children: ReactNode }) {
  const session = await getPublicSession();

  return (
    <PublicShell session={session} hideFooter>
      {children}
    </PublicShell>
  );
}
