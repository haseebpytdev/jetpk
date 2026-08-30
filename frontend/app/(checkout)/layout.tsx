import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import type { PublicSession } from "@/types/session";

/**
 * Checkout soft-nav layout (Book Now → Traveler).
 *
 * Do NOT SSR-fetch Laravel session/config here. R6 telemetry proved passengers
 * API is ~150–1100ms while T7→T8 soft-nav still spikes ~25–30s when layout
 * awaits getPublicSession (AbortSignal.timeout did not eliminate outliers).
 *
 * Anonymous SSR shell paints immediately; guest checkout is the primary path.
 * Authenticated header state is not required to enter Traveler.
 */
export const dynamic = "force-dynamic";

const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export default function CheckoutLayout({ children }: { children: ReactNode }) {
  return (
    <PublicShell session={ANONYMOUS_SESSION} hideFooter>
      {children}
    </PublicShell>
  );
}
