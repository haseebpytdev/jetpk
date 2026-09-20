import type { Metadata } from "next";
import { Suspense } from "react";
import { AboutPageContent, PublicPageService, publicSeoToMetadata } from "@/features/public-content";
import AboutLoading from "./loading";

export const revalidate = 300;

export async function generateMetadata(): Promise<Metadata> {
  const page = await PublicPageService.getAboutPage();
  return publicSeoToMetadata(page.seo, "/about-us");
}

async function AboutUsContent() {
  const page = await PublicPageService.getAboutPage();
  return <AboutPageContent page={page} />;
}

export default function AboutUsPage() {
  return (
    <Suspense fallback={<AboutLoading />}>
      <AboutUsContent />
    </Suspense>
  );
}
