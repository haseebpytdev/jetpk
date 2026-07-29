import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  CustomClientPageRenderer,
  CustomPageService,
  isReservedPublicSlug,
  publicSeoToMetadata,
} from "@/features/public-content";

type CustomSlugPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({ params }: CustomSlugPageProps): Promise<Metadata> {
  const { slug } = await params;
  if (isReservedPublicSlug(slug)) {
    return { title: "Page not found" };
  }

  const page = await CustomPageService.getBySlug(slug);
  if (!page) return { title: "Page not found" };

  return publicSeoToMetadata(page.seo, `/${slug}`);
}

export default async function CustomSlugPage({ params }: CustomSlugPageProps) {
  const { slug } = await params;
  if (isReservedPublicSlug(slug)) {
    notFound();
  }

  const page = await CustomPageService.getBySlug(slug);
  if (!page) {
    notFound();
  }

  return <CustomClientPageRenderer page={page} />;
}
