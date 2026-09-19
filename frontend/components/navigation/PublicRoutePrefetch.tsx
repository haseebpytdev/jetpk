"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { registerPublicPrefetchImpl } from "@/components/navigation/public-prefetch-coordinator";

/**
 * Soft-nav: register router.prefetch for PrefetchOnIntentLink only.
 * Background queue disabled — hover/focus intent warms the destination
 * without remount stampede (stage-decomp).
 */
export function PublicRoutePrefetch() {
  const router = useRouter();

  useEffect(() => {
    registerPublicPrefetchImpl((href) => {
      void router.prefetch(href);
    });
  }, [router]);

  return null;
}
