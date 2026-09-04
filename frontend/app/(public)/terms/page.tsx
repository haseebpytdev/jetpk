import type { Metadata } from "next";
import { LegalDocumentLayout, LegalPageService, publicSeoToMetadata } from "@/features/public-content";

export const revalidate = 60;

export async function generateMetadata(): Promise<Metadata> {
  const document = await LegalPageService.getTerms();
  return publicSeoToMetadata(document.seo, "/terms");
}

export default async function TermsPage() {
  const document = await LegalPageService.getTerms();
  return <LegalDocumentLayout document={document} breadcrumbLabel="Terms" />;
}
