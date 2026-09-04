import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import type { PublicSession } from "@/types/session";

/**
 * Published homepage — static anonymous shell + ISR content.
 * Do NOT force-dynamic or SSR-await session/config (soft-nav from "/" was blocked).
 * CMS draft preview: /home/preview
 */
const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export const revalidate = 60;

export default function HomePage() {
  return (
    <PublicShell session={ANONYMOUS_SESSION}>
      <HomepageContent />
    </PublicShell>
  );
}
