"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

/** Soft-nav CTAs: warm immediately so early header/footer clicks reuse RSC. */
const PRIORITY_PREFETCH_ROUTES = ["/login", "/register", "/privacy", "/faq", "/terms"] as const;

/** Remaining public routes — deferred idle queue (staggered). */
const PREFETCH_ROUTES = [
  "/groups/search",
  "/about-us",
  "/contact",
  "/support",
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

    // Header + footer legal routes — prefetch immediately so early soft-nav avoids cold RSC.
    for (const href of PRIORITY_PREFETCH_ROUTES) prefetchHref(href);

    const ric = (window as Window & {
      requestIdleCallback?: (cb: () => void, opts?: { timeout?: number }) => number;
    }).requestIdleCallback;

    // Deferred queue only for lower-priority paths; legal routes already priority-warmed.
    window.setTimeout(() => {
      if (typeof ric === "function") {
        ric(startDeferredQueue, { timeout: 2500 });
      } else {
        startDeferredQueue();
      }
    }, 800);
  }, [router]);

  return null;
}
