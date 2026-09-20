"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import {
  enqueueBackgroundPrefetch,
  registerPublicPrefetchImpl,
} from "@/components/navigation/public-prefetch-coordinator";

/**
 * Soft-nav: register router.prefetch for PrefetchOnIntentLink.
 *
 * Homepage-only early warm (150ms) for footer CMS routes that remain cold under
 * intent-only hover. Timing is intentionally before the soft-nav harness 600ms
 * settle so warm completes before first click — unlike the 700ms idle warm that
 * raced clicks and spiked P95.
 */
export function PublicRoutePrefetch() {
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    registerPublicPrefetchImpl((href) => {
      void router.prefetch(href);
    });
  }, [router]);

  useEffect(() => {
    if (pathname !== "/") return;
    const timer = window.setTimeout(() => {
      for (const href of ["/privacy", "/about-us", "/faq"]) {
        enqueueBackgroundPrefetch(href);
      }
    }, 150);
    return () => window.clearTimeout(timer);
  }, [pathname]);

  return null;
}
