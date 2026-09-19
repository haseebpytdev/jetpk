"use client";

import { useEffect } from "react";
import { fetchSessionBootstrap } from "@/features/auth/services/session-service";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";

/**
 * Soft-nav: keep /login and /register RSC prefetchable (no cookies() on the page).
 * Authenticated visitors are redirected after hydration — same authority as prior SSR gate.
 */
export function AuthAlreadySignedInRedirect() {
  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const bootstrap = await fetchSessionBootstrap();
        if (cancelled || !bootstrap.authenticated) return;
        const dest = sanitizeDashboardUrl(
          bootstrap.landing_route ?? bootstrap.dashboard_url,
          "/",
        );
        window.location.replace(dest);
      } catch {
        /* stay on auth form */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  return null;
}
