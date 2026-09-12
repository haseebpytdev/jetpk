"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

/** Soft-nav CTAs: warm immediately so early header clicks reuse RSC. */
const PRIORITY_PREFETCH_ROUTES = ["/login", "/register"] as const;

const PREFETCH_ROUTES = [
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

    const prefetchHref = (href: string) => {
      try {
        void router.prefetch(href);
      } catch {
        /* best-effort */
      }
    };

    const prefetchNext = () => {
      if (index >= PREFETCH_ROUTES.length) return;
      const href = PREFETCH_ROUTES[index++];
      prefetchHref(href);
      // Wide stagger: soft-nav RSC must not share the pipe with a prefetch burst.
      window.setTimeout(prefetchNext, 350);
    };

    const startDeferredQueue = () => {
      prefetchNext();
    };

    // Login/register are header CTAs — prefetch immediately on mount (no timer) so 0ms-delay clicks reuse RSC.
    for (const href of PRIORITY_PREFETCH_ROUTES) prefetchHref(href);

    const ric = (window as Window & {
      requestIdleCallback?: (cb: () => void, opts?: { timeout?: number }) => number;
    }).requestIdleCallback;

    window.setTimeout(() => {
      if (typeof ric === "function") {
        ric(startDeferredQueue, { timeout: 4000 });
      } else {
        startDeferredQueue();
      }
    }, 2500);
  }, [router]);

  return null;
}
