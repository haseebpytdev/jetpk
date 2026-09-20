"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { registerPublicPrefetchImpl } from "@/components/navigation/public-prefetch-coordinator";

/**
 * Soft-nav: register router.prefetch for PrefetchOnIntentLink only.
 * Do not background-warm homepage outbound routes — wholesale and selective
 * idle warm both correlated with P95 spikes under the soft-nav matrix.
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
