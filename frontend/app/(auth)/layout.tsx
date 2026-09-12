import type { ReactNode } from "react";
import { AuthRouteLayoutShell } from "@/components/layout/AuthRouteLayoutShell";
import { AuthCsrfBootstrap } from "@/features/auth/components/AuthCsrfBootstrap";

/**
 * Minimal AuthRouteLayoutShell — no PublicShell marketing stack (AI chat,
 * route prefetch, full nav/footer). GuestAuthRedirect on login/register
 * still sends signed-in users away.
 */
export default function AuthGroupLayout({ children }: { children: ReactNode }) {
  return (
    <AuthRouteLayoutShell>
      <AuthCsrfBootstrap />
      {children}
    </AuthRouteLayoutShell>
  );
}
