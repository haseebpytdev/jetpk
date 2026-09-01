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
 * Anonymous shell is static — do NOT set force-dynamic. R6H proved that
 * force-dynamic alone reintroduced bimodal T7→T8 tails (~0.5s vs ~15–34s)
 * because every router.push waited on an RSC server round-trip for a layout
 * that never varies.
 *
 * Guest checkout is the primary path; authenticated header is not required.
 * Shared SiteFooter stays enabled for public footer consistency across Traveler /
 * Review / Payment shells (sticky CTAs keep their own bottom padding).
 */
const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export default function CheckoutLayout({ children }: { children: ReactNode }) {
  return (
    <PublicShell session={ANONYMOUS_SESSION}>
      {children}
    </PublicShell>
  );
}
