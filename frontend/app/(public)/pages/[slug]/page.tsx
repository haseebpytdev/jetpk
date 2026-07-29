import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { CmsPageRenderer, CmsPageService, publicSeoToMetadata } from "@/features/public-content";

type CmsSlugPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({ params }: CmsSlugPageProps): Promise<Metadata> {
  const { slug } = await params;
  const page = await CmsPageService.getBySlug(slug);
  if (!page) return { title: "Page not found" };

  return publicSeoToMetadata(
    {
      title: page.seo.title || page.title,
      description: page.seo.description,
      robots: page.seo.robots,
      canonical: page.seo.canonical,
      og_title: page.seo.og_title,
      og_description: page.seo.og_description,
      og_image: page.seo.og_image,
    },
    `/pages/${slug}`,
  );
}

export default async function CmsSlugPage({ params }: CmsSlugPageProps) {
  const { slug } = await params;
  const page = await CmsPageService.getBySlug(slug);
  if (!page) notFound();

  return <CmsPageRenderer page={page} />;
}
