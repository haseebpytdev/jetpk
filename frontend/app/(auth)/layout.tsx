import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { AuthCsrfBootstrap } from "@/features/auth/components/AuthCsrfBootstrap";
import { AuthMediaProvider } from "@/features/auth/components/AuthMediaProvider";
import { getAuthIllustrationMedia } from "@/features/auth/services/auth-page-media";
import type { PublicSession } from "@/types/session";

/**
 * Auth routes (/login, /register, …).
 *
 * Same soft-nav rule as checkout/(public): static anonymous shell, no
 * force-dynamic, no SSR session/config await. PublicShell upgrades header
 * after mount; GuestAuthRedirect on login/register sends signed-in users away.
 */
const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export const revalidate = 300;

export default async function AuthGroupLayout({ children }: { children: ReactNode }) {
  const illustration = await getAuthIllustrationMedia();

  return (
    <PublicShell session={ANONYMOUS_SESSION}>
      <AuthCsrfBootstrap />
      <AuthMediaProvider illustration={illustration}>{children}</AuthMediaProvider>
    </PublicShell>
  );
}
