import type { Metadata } from "next";
import { LegalDocumentLayout, LegalPageService } from "@/features/public-content";

export async function generateMetadata(): Promise<Metadata> {
  const document = await LegalPageService.getTerms();
  return {
    title: document.seo.title,
    description: document.seo.description,
    robots: document.seo.robots,
  };
}

export default async function TermsPage() {
  const document = await LegalPageService.getTerms();
  return <LegalDocumentLayout document={document} breadcrumbLabel="Terms" />;
}
