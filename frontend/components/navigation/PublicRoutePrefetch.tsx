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

/** Module-scoped: PublicShell remounts on every soft-nav; do not re-stampede RSC. */
let publicRoutesPrefetchStarted = false;

/**
 * Idle-prefetch ordinary public routes once per tab so soft-nav shell stays warm
 * without re-flooding ?_rsc fetches after every PublicShell remount.
 */
export function PublicRoutePrefetch() {
  const router = useRouter();

  useEffect(() => {
    if (publicRoutesPrefetchStarted) return;
    publicRoutesPrefetchStarted = true;

    let cancelled = false;
    let index = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let idleId: number | null = null;

    const prefetchNext = () => {
      if (cancelled || index >= PREFETCH_ROUTES.length) return;
      const href = PREFETCH_ROUTES[index++];
      try {
        void router.prefetch(href);
      } catch {
        /* best-effort */
      }
      // Wide stagger: soft-nav RSC must not share the pipe with a prefetch burst.
      timer = setTimeout(prefetchNext, 350);
    };

    const start = () => {
      if (cancelled) return;
      prefetchNext();
    };

    // Delay past first paint/hydration so an early Link click is not contended
    // with our own idle prefetch queue (login/about/contact/…).
    const ric = (window as Window & {
      requestIdleCallback?: (cb: () => void, opts?: { timeout?: number }) => number;
    }).requestIdleCallback;
    const kickoff = () => {
      if (typeof ric === "function") {
        idleId = ric(start, { timeout: 4000 });
      } else {
        start();
      }
    };
    timer = setTimeout(kickoff, 2500);

    return () => {
      cancelled = true;
      if (timer) window.clearTimeout(timer);
      if (idleId != null) {
        (window as Window & { cancelIdleCallback?: (id: number) => void }).cancelIdleCallback?.(idleId);
      }
    };
  }, [router]);

  return null;
}
