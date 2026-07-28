import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { CmsPageRenderer, CmsPageService } from "@/features/public-content";

type CmsSlugPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({ params }: CmsSlugPageProps): Promise<Metadata> {
  const { slug } = await params;
  const page = await CmsPageService.getBySlug(slug);
  if (!page) return { title: "Page not found" };

  return {
    title: page.seo.title || page.title,
    description: page.seo.description,
    robots: page.seo.robots,
  };
}

export default async function CmsSlugPage({ params }: CmsSlugPageProps) {
  const { slug } = await params;
  const page = await CmsPageService.getBySlug(slug);
  if (!page) notFound();

  return <CmsPageRenderer page={page} />;
}
