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

/** Idle-prefetch ordinary public routes so soft-nav shell stays warm. */
export function PublicRoutePrefetch() {
  const router = useRouter();

  useEffect(() => {
    let cancelled = false;
    const run = () => {
      if (cancelled) return;
      for (const href of PREFETCH_ROUTES) {
        try {
          void router.prefetch(href);
        } catch {
          /* best-effort */
        }
      }
    };
    const ric = (window as Window & { requestIdleCallback?: (cb: () => void) => number }).requestIdleCallback;
    if (typeof ric === "function") {
      const id = ric(run);
      return () => {
        cancelled = true;
        (window as Window & { cancelIdleCallback?: (id: number) => void }).cancelIdleCallback?.(id);
      };
    }
    const t = window.setTimeout(run, 400);
    return () => {
      cancelled = true;
      window.clearTimeout(t);
    };
  }, [router]);

  return null;
}
