"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

const PREFETCH_ROUTES = [
  "/login",
  "/register",
  "/groups",
  "/about-us",
  "/contact",
  "/faq",
  "/support",
  "/privacy",
  "/terms",
] as const;

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

    let index = 0;

    const prefetchNext = () => {
      if (index >= PREFETCH_ROUTES.length) return;
      const href = PREFETCH_ROUTES[index++];
      try {
        void router.prefetch(href);
      } catch {
        /* best-effort */
      }
      // Wide stagger: soft-nav RSC must not share the pipe with a prefetch burst.
      window.setTimeout(prefetchNext, 350);
    };

    const start = () => {
      prefetchNext();
    };

    // Delay past first paint/hydration so an early Link click is not contended
    // with our own idle prefetch queue (login/about/contact/…).
    const ric = (window as Window & {
      requestIdleCallback?: (cb: () => void, opts?: { timeout?: number }) => number;
    }).requestIdleCallback;

    window.setTimeout(() => {
      if (typeof ric === "function") {
        ric(start, { timeout: 4000 });
      } else {
        start();
      }
    }, 2500);
  }, [router]);

  return null;
}
