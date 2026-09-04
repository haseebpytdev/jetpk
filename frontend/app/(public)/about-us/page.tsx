import type { Metadata } from "next";
import { AboutPageContent, PublicPageService, publicSeoToMetadata } from "@/features/public-content";

/**
 * Published About is ISR-cached for soft-nav.
 * CMS preview lives at /about-us/preview (force-dynamic) so this route stays cacheable.
 */
export const revalidate = 60;

export async function generateMetadata(): Promise<Metadata> {
  const page = await PublicPageService.getAboutPage();
  return publicSeoToMetadata(page.seo, "/about-us");
}

export default async function AboutUsPage() {
  const page = await PublicPageService.getAboutPage();
  return <AboutPageContent page={page} />;
}
