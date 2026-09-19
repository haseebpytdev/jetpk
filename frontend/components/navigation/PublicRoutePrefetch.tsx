"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

/**
 * Soft-nav warmth: single-flight RSC prefetch owned here only.
 * Footer/header Links use prefetch={false} so they do not stampede with this queue
 * (stage-decomp: concurrent Link prefetch + PublicRoutePrefetch starved destination RSC).
 *
 * /contact omitted — it 308s to /about-us and wastes a slot.
 */
const PREFETCH_QUEUE = [
  "/privacy",
  "/faq",
  "/terms",
  "/login",
  "/register",
  "/about-us",
  "/support",
  "/groups/search",
] as const;

const STAGGER_MS = 280;

/** Module-scoped: survives PublicShell remounts; never re-stampede RSC. */
let publicRoutesPrefetchStarted = false;

/**
 * Once-per-tab sequential prefetch so soft-nav soft clicks reuse warm Flight
 * without flooding ?_rsc under the cert harness early-click window.
 */
export function PublicRoutePrefetch() {
  const router = useRouter();

  useEffect(() => {
    if (publicRoutesPrefetchStarted) return;
    publicRoutesPrefetchStarted = true;

    let index = 0;
    let cancelled = false;

    const prefetchNext = () => {
      if (cancelled || index >= PREFETCH_QUEUE.length) return;
      const href = PREFETCH_QUEUE[index++];
      try {
        void router.prefetch(href);
      } catch {
        /* best-effort */
      }
      window.setTimeout(prefetchNext, STAGGER_MS);
    };

    // Small delay so first paint / LCP is not competing with the first RSC prefetch.
    window.setTimeout(prefetchNext, 120);

    return () => {
      cancelled = true;
    };
  }, [router]);

  return null;
}
