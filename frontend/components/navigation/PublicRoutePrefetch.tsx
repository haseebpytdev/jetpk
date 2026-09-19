"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import {
  enqueueBackgroundPrefetch,
  registerPublicPrefetchImpl,
} from "@/components/navigation/public-prefetch-coordinator";

/**
 * Soft-nav warmth: single-flight RSC prefetch owned by public-prefetch-coordinator.
 * Chrome links use PrefetchOnIntentLink (intent preempts this queue).
 *
 * /contact omitted — it 308s to /about-us and wastes a slot.
 */
const PREFETCH_QUEUE = [
  "/privacy",
  "/faq",
  "/terms",
  "/support",
  "/groups/search",
  "/login",
  "/about-us",
  "/register",
] as const;

const STAGGER_MS = 220;
const START_DELAY_MS = 800;

/** Module-scoped: survives PublicShell remounts; never re-stampede RSC. */
let publicRoutesPrefetchStarted = false;

/**
 * Once-per-tab sequential prefetch so soft-nav soft clicks reuse warm Flight
 * without flooding ?_rsc under the cert harness early-click window.
 */
export function PublicRoutePrefetch() {
  const router = useRouter();

  useEffect(() => {
    registerPublicPrefetchImpl((href) => {
      void router.prefetch(href);
    });

    if (publicRoutesPrefetchStarted) return;
    publicRoutesPrefetchStarted = true;

    let index = 0;
    let cancelled = false;

    const enqueueNext = () => {
      if (cancelled || index >= PREFETCH_QUEUE.length) return;
      enqueueBackgroundPrefetch(PREFETCH_QUEUE[index++]);
      window.setTimeout(enqueueNext, STAGGER_MS);
    };

    // Delay so first paint / LCP and early soft-nav clicks are not starved.
    window.setTimeout(enqueueNext, START_DELAY_MS);

    return () => {
      cancelled = true;
    };
  }, [router]);

  return null;
}
