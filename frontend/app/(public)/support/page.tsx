import type { Metadata } from "next";
import { Suspense } from "react";
import { PageContainer } from "@/components/layout/PageContainer";
import {
  Breadcrumbs,
  SupportContentService,
  SupportPageClient,
  fetchSupportCategories,
} from "@/features/public-content";
import SupportLoading from "./loading";

/** Soft-nav ISR: keep CMS support payload warm for CLIENT_SOFT transitions. */
export const revalidate = 300;
export const dynamic = "force-static";

/**
 * Soft-nav: static metadata so URL commit is not blocked on Support CMS fetch.
 * Canonical CMS SEO still renders with the streamed body.
 */
export const metadata: Metadata = {
  title: "Support | JetPakistan",
  description: "Get help with flights, bookings, payments, and travel support from JetPakistan.",
  robots: { index: true, follow: true },
  alternates: { canonical: "/support" },
};

async function SupportPageContent() {
  // Support CMS body is critical for soft-nav usable; categories must not stall RSC.
  const content = await SupportContentService.getSupportPage();
  const categories = await Promise.race([
    fetchSupportCategories(),
    new Promise<Awaited<ReturnType<typeof fetchSupportCategories>>>((resolve) => {
      setTimeout(() => resolve([]), 250);
    }),
  ]);

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "Support" }]} />
      <div className="mt-jp-xl">
        <SupportPageClient
          content={content}
          categories={
            categories.length
              ? categories
              : [
                  { value: "booking", label: "Booking" },
                  { value: "payment", label: "Payment" },
                  { value: "technical", label: "Technical" },
                  { value: "other", label: "Other" },
                ]
          }
        />
      </div>
    </PageContainer>
  );
}

/** Suspense so soft-nav URL commits while CMS streams. */
export default function SupportPage() {
  return (
    <Suspense fallback={<SupportLoading />}>
      <SupportPageContent />
    </Suspense>
  );
}
