import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { AuthCsrfBootstrap } from "@/features/auth/components/AuthCsrfBootstrap";
import type { PublicSession } from "@/types/session";

/**
 * Auth routes (/login, /register, …).
 *
 * Same soft-nav rule as checkout/(public): static anonymous shell, no
 * force-dynamic, no SSR session/config await. PublicShell upgrades header
 * after mount; GuestAuthRedirect on login/register sends signed-in users away.
 */
const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

export default function AuthGroupLayout({ children }: { children: ReactNode }) {
  return (
    <PublicShell session={ANONYMOUS_SESSION}>
      <AuthCsrfBootstrap />
      {children}
    </PublicShell>
  );
}
