import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { SeoJsonLd } from "@/features/public-content";
import type { PublicSession } from "@/types/session";

/**
 * Public informational routes (About, FAQ, Groups, Contact, …).
 *
 * Do NOT SSR-await Laravel session/config here and do NOT force-dynamic.
 * Soft-nav RSC previously waited on session + no-store config on every Link
 * click (multi-second ordinary-page P95). Checkout layout already proved the
 * anonymous static shell pattern; PublicShell hydrates session/branding client-side.
 */
const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export default function PublicGroupLayout({ children }: { children: ReactNode }) {
  return (
    <PublicShell session={ANONYMOUS_SESSION}>
      <SeoJsonLd config={null} />
      {children}
    </PublicShell>
  );
}
