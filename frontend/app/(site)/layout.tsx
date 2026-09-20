import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { SeoJsonLd } from "@/features/public-content";
import type { PublicSession } from "@/types/session";

/**
 * Shared marketing chrome for (public) + (auth).
 * Soft-nav home→login must NOT remount PublicShell (sibling route-group remount).
 * No server PublicConfig await — PublicShell hydrates branding/session client-side.
 */
export const revalidate = 60;

const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export default function SiteLayout({ children }: { children: ReactNode }) {
  return (
    <PublicShell session={ANONYMOUS_SESSION} branding={null} aiEnabled={false}>
      <SeoJsonLd config={null} />
      {children}
    </PublicShell>
  );
}
