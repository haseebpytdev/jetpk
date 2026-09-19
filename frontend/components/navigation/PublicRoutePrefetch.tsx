"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

/**
 * Soft-nav CTAs: warm in two immediate waves so legal + auth stay first
 * while about/support/contact/groups follow shortly after.
 * Wave-2 is delayed briefly to avoid stampeding RSC with the first wave
 * (08602d4a concurrent-priority regressing home_faq P95).
 */
const PRIORITY_PREFETCH_ROUTES_WAVE1 = ["/privacy", "/faq", "/terms", "/login", "/register"] as const;
const PRIORITY_PREFETCH_ROUTES_WAVE2 = ["/about-us", "/support", "/contact", "/groups/search"] as const;

/** Module-scoped: survives PublicShell remounts; never re-stampede RSC. */
let publicRoutesPrefetchStarted = false;

/**
 * Idle-prefetch ordinary public routes once per tab so soft-nav shell stays warm
 * without re-flooding ?_rsc fetches after every PublicShell remount.
 *
 * Timers are intentionally module-owned: unmount during soft-nav must not
 * cancel the once-per-tab queue (and must not restart it).
 */
export function PublicRoutePrefetch() {
  const router = useRouter();

  useEffect(() => {
    if (publicRoutesPrefetchStarted) return;
    publicRoutesPrefetchStarted = true;

    const prefetchHref = (href: string) => {
      try {
        void router.prefetch(href);
      } catch {
        /* best-effort */
      }
    };

    // Wave 1: header/footer legal + auth (highest soft-nav volume).
    for (const href of PRIORITY_PREFETCH_ROUTES_WAVE1) prefetchHref(href);
    // Wave 2: about/support/contact/groups after a short gap (avoids RSC stampede).
    window.setTimeout(() => {
      for (const href of PRIORITY_PREFETCH_ROUTES_WAVE2) prefetchHref(href);
    }, 220);
  }, [router]);

  return null;
}
