"use client";

import { useEffect, useState } from "react";
import { AboutPageContent } from "@/features/public-content";
import { loadAboutPageBrowser } from "@/features/public-content/services/about-content-browser";
import type { PublicPage } from "@/features/public-content";
import AboutLoading from "./loading";

/**
 * Soft-nav: client CMS load so home→/about-us Flight has no Laravel awaits.
 */
export function AboutClientPage() {
  const [page, setPage] = useState<PublicPage | null>(null);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const next = await loadAboutPageBrowser();
        if (!cancelled) setPage(next);
      } catch {
        /* keep loading shell */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  if (!page) {
    return <AboutLoading />;
  }

  return <AboutPageContent page={page} />;
}
