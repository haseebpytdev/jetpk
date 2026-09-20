import type { Metadata } from "next";
import { Suspense } from "react";
import { AboutPageContent, PublicPageService } from "@/features/public-content";
import AboutLoading from "./loading";

export const revalidate = 300;

/**
 * Soft-nav: static metadata so URL commit is not blocked on About CMS fetch.
 * Canonical CMS SEO still renders with the streamed body.
 */
export const metadata: Metadata = {
  title: "About Us | JetPakistan",
  description: "Learn about JetPakistan — flights, Umrah packages, and travel support across Pakistan.",
  robots: { index: true, follow: true },
  alternates: { canonical: "/about-us" },
};

async function AboutUsContent() {
  const page = await PublicPageService.getAboutPage();
  return <AboutPageContent page={page} />;
}

/** Soft-nav: Suspense so URL commits while CMS streams. */
export default function AboutUsPage() {
  return (
    <Suspense fallback={<AboutLoading />}>
      <AboutUsContent />
    </Suspense>
  );
}
