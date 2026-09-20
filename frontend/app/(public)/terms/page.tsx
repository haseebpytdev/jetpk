import type { Metadata } from "next";
import { Suspense } from "react";
import { LegalDocumentLayout, LegalPageService } from "@/features/public-content";
import LegalLoading from "./loading";

export const revalidate = 300;
export const dynamic = "force-static";

/**
 * Soft-nav: static metadata so URL commit is not blocked on LegalPageService.
 * Canonical CMS SEO still renders with the streamed body.
 */
export const metadata: Metadata = {
  title: "Terms of Service | JetPakistan",
  description: "Read the JetPakistan terms of service for bookings, payments, and travel products.",
  robots: { index: true, follow: true },
  alternates: { canonical: "/terms" },
};

async function TermsBody() {
  const document = await LegalPageService.getTerms();
  return <LegalDocumentLayout document={document} breadcrumbLabel="Terms" />;
}

/** Soft-nav: Suspense so URL commits while legal CMS streams (privacy pattern). */
export default function TermsPage() {
  return (
    <Suspense fallback={<LegalLoading />}>
      <TermsBody />
    </Suspense>
  );
}
