import type { Metadata } from "next";
import { LegalDocumentLayout, LegalPageService, publicSeoToMetadata } from "@/features/public-content";

export async function generateMetadata(): Promise<Metadata> {
  const document = await LegalPageService.getPrivacy();
  return publicSeoToMetadata(document.seo, "/privacy");
}

export default async function PrivacyPage() {
  const document = await LegalPageService.getPrivacy();
  return <LegalDocumentLayout document={document} breadcrumbLabel="Privacy" />;
}
