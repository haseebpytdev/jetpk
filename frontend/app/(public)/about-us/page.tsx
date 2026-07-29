import type { Metadata } from "next";
import { AboutPageContent, PublicPageService, publicSeoToMetadata } from "@/features/public-content";

export async function generateMetadata(): Promise<Metadata> {
  const page = await PublicPageService.getAboutPage();
  return publicSeoToMetadata(page.seo, "/about-us");
}

export default async function AboutUsPage() {
  const page = await PublicPageService.getAboutPage();
  return <AboutPageContent page={page} />;
}
