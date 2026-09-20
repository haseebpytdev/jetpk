"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import {
  enqueueBackgroundPrefetch,
  registerPublicPrefetchImpl,
} from "@/components/navigation/public-prefetch-coordinator";

/**
 * Soft-nav: register router.prefetch for PrefetchOnIntentLink.
 * On homepage only, after a short idle, warm the three historically cold
 * outbound targets (support / groups / login) — not the full CMS matrix that
 * spiked P95 when background-warmed wholesale on 066d8697.
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
      for (const href of ["/support", "/groups/search", "/login"]) {
        enqueueBackgroundPrefetch(href);
      }
    }, 700);
    return () => window.clearTimeout(timer);
  }, [pathname]);

  return null;
}
