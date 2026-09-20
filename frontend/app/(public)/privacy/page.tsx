import type { Metadata } from "next";
import { Suspense } from "react";
import { LegalDocumentLayout, LegalPageService } from "@/features/public-content";
import PrivacyLoading from "./loading";

export const revalidate = 300;

/**
 * Soft-nav: static metadata so URL commit is not blocked on LegalPageService.
 * Canonical CMS SEO still renders with the streamed body.
 */
export const metadata: Metadata = {
  title: "Privacy Policy | JetPakistan",
  description: "Read how JetPakistan collects, uses, and protects your personal information.",
  robots: { index: true, follow: true },
  alternates: { canonical: "/privacy" },
};

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
