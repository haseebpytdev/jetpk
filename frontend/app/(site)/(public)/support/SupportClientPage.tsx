"use client";

import { useEffect, useState } from "react";
import { PageContainer } from "@/components/layout/PageContainer";
import { Breadcrumbs, SupportPageClient } from "@/features/public-content";
import { loadSupportPageBrowser } from "@/features/public-content/services/support-content-browser";
import { publicContentFetchUrl } from "@/features/public-content/utils/laravel-api-url";
import type { SupportPageContent, SupportTicketCategoryOption } from "@/features/public-content";
import SupportLoading from "./loading";

const FALLBACK_CATEGORIES: SupportTicketCategoryOption[] = [
  { value: "booking", label: "Booking" },
  { value: "payment", label: "Payment" },
  { value: "technical", label: "Technical" },
  { value: "other", label: "Other" },
];

/**
 * Soft-nav: client CMS load so home→/support Flight has no Laravel awaits.
 */
export function SupportClientPage() {
  const [content, setContent] = useState<SupportPageContent | null>(null);
  const [categories, setCategories] = useState<SupportTicketCategoryOption[]>(FALLBACK_CATEGORIES);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const [page, catsRes] = await Promise.all([
          loadSupportPageBrowser(),
          fetch(publicContentFetchUrl("/api/public/content/support/categories"), {
            headers: { Accept: "application/json" },
            credentials: "include",
            cache: "no-store",
          })
            .then(async (r) => (r.ok ? ((await r.json()) as { categories?: SupportTicketCategoryOption[] }) : null))
            .catch(() => null),
        ]);
        if (cancelled) return;
        setContent(page);
        if (catsRes?.categories?.length) setCategories(catsRes.categories);
      } catch {
        /* keep loading shell */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  if (!content) {
    return <SupportLoading />;
  }

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "Support" }]} />
      <div className="mt-jp-xl">
        <SupportPageClient content={content} categories={categories} />
      </div>
    </PageContainer>
  );
}
