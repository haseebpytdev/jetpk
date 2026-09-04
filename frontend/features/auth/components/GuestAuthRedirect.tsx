"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { fetchSessionBootstrap, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";

type GuestAuthRedirectProps = {
  /** Preferred post-auth destination when already signed in. */
  returnPath?: string;
};

/**
 * Client redirect for already-authenticated visitors on guest auth pages.
 * Keeps /login and /register SSR free of cookies()/session awaits so soft-nav
 * is not blocked on Laravel session (layout is static anonymous shell).
 */
export function GuestAuthRedirect({ returnPath = "" }: GuestAuthRedirectProps) {
  const router = useRouter();

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const bootstrap = await fetchSessionBootstrap();
        if (cancelled || !bootstrap.authenticated) return;
        const session = mapBootstrapToPublicSession(bootstrap);
        if (session.status !== "authenticated") return;
        const dest =
          returnPath ||
          sanitizeDashboardUrl(session.landingRoute ?? session.dashboardUrl, "/");
        router.replace(dest);
      } catch {
        /* guest path continues */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [returnPath, router]);

  return null;
}
