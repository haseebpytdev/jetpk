import type { Metadata } from "next";
import { Suspense } from "react";
import { LegalDocumentLayout, LegalPageService, publicSeoToMetadata } from "@/features/public-content";
import LegalLoading from "./loading";

export const revalidate = 300;

export async function generateMetadata(): Promise<Metadata> {
  const document = await LegalPageService.getTerms();
  return publicSeoToMetadata(document.seo, "/terms");
}

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
