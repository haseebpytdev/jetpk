import type { Metadata } from "next";
import { LegalDocumentLayout, LegalPageService } from "@/features/public-content";

export async function generateMetadata(): Promise<Metadata> {
  const document = await LegalPageService.getPrivacy();
  return {
    title: document.seo.title,
    description: document.seo.description,
    robots: document.seo.robots,
  };
}

export default async function PrivacyPage() {
  const document = await LegalPageService.getPrivacy();
  return <LegalDocumentLayout document={document} breadcrumbLabel="Privacy" />;
}
