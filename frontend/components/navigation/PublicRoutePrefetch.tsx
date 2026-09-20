"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import {
  enqueueBackgroundPrefetch,
  registerPublicPrefetchImpl,
} from "@/components/navigation/public-prefetch-coordinator";

/** Soft-nav destinations measured in the public APP P95 matrix. */
const SOFT_NAV_WARM_ROUTES = [
  "/support",
  "/privacy",
  "/terms",
  "/faq",
  "/about-us",
  "/groups/search",
  "/login",
  "/register",
] as const;

/**
 * Soft-nav: register router.prefetch for PrefetchOnIntentLink.
 * On the homepage, enqueue a delayed serial warm of soft-nav matrix routes so
 * the first click after paint is less likely to pay a cold Flight cost.
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
      for (const href of SOFT_NAV_WARM_ROUTES) {
        enqueueBackgroundPrefetch(href);
      }
    }, 450);
    return () => window.clearTimeout(timer);
  }, [pathname]);

  return null;
}
