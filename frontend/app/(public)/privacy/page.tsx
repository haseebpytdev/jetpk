import type { Metadata } from "next";
import { Suspense } from "react";
import { LegalDocumentLayout, LegalPageService, publicSeoToMetadata } from "@/features/public-content";
import PrivacyLoading from "./loading";

export const revalidate = 300;

export async function generateMetadata(): Promise<Metadata> {
  const document = await LegalPageService.getPrivacy();
  return publicSeoToMetadata(document.seo, "/privacy");
}

async function PrivacyBody() {
  const document = await LegalPageService.getPrivacy();
  return <LegalDocumentLayout document={document} breadcrumbLabel="Privacy" />;
}

/** Soft-nav: Suspense so URL commits while legal CMS streams (FAQ/support pattern). */
export default function PrivacyPage() {
  return (
    <Suspense fallback={<PrivacyLoading />}>
      <PrivacyBody />
    </Suspense>
  );
}
