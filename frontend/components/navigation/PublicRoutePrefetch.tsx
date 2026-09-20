"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { registerPublicPrefetchImpl } from "@/components/navigation/public-prefetch-coordinator";

/**
 * Soft-nav: register router.prefetch for PrefetchOnIntentLink only.
 * Background homepage warm was tried on 066d8697 and correlated with large
 * outbound P95 outliers under the soft-nav matrix — keep intent-only.
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
