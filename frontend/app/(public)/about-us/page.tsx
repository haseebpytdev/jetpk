import type { Metadata } from "next";
import { AboutPageContent, PublicPageService } from "@/features/public-content";

export async function generateMetadata(): Promise<Metadata> {
  const page = await PublicPageService.getAboutPage();
  return {
    title: page.seo.title,
    description: page.seo.description,
    robots: page.seo.robots,
  };
}

export default async function AboutUsPage() {
  const page = await PublicPageService.getAboutPage();
  return <AboutPageContent page={page} />;
}
