import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { PageContainer } from "@/components/layout/PageContainer";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { Breadcrumbs, FaqPageClient, FaqService, PublicPageHero } from "@/features/public-content";
import FaqLoading from "./loading";

export const revalidate = 300;

/**
 * Soft-nav: keep generateMetadata free of FaqService await so cold navigations
 * are not blocked on a duplicate CMS fetch before the Suspense body streams.
 * Canonical CMS SEO remains on the streamed body via FaqPageContent.
 */
export const metadata: Metadata = {
  title: "FAQ | JetPakistan",
  description: "Frequently asked questions about flights, bookings, payments, and travel with JetPakistan.",
  robots: { index: true, follow: true },
  alternates: { canonical: "/faq" },
};

async function FaqPageContent() {
  const page = await FaqService.getFaqPage();

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "FAQ" }]} />
      <div className="mt-jp-xl space-y-jp-2xl">
        <PublicPageHero hero={page.hero} id="faq-page-heading" />
        <FaqPageClient categories={page.categories} />
        {page.cta ? (
          <div>
            <Link href={page.cta.href}>
              <PrimaryButton>{page.cta.label}</PrimaryButton>
            </Link>
          </div>
        ) : null}
      </div>
    </PageContainer>
  );
}

/** Suspense so soft-nav URL commits while CMS streams. */
export default function FaqPage() {
  return (
    <Suspense fallback={<FaqLoading />}>
      <FaqPageContent />
    </Suspense>
  );
}
