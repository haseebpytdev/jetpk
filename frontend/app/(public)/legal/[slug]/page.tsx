import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  CustomClientPageRenderer,
  CustomPageService,
  publicSeoToMetadata,
} from "@/features/public-content";

const LEGAL_SLUG_MAP: Record<string, string> = {
  refund: "refund-policy",
  cookies: "cookie-policy",
  cancellation: "cancellation-policy",
  "booking-terms": "booking-terms",
};

type LegalSlugPageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({ params }: LegalSlugPageProps): Promise<Metadata> {
  const { slug } = await params;
  const cmsSlug = LEGAL_SLUG_MAP[slug] ?? slug;
  const page = await CustomPageService.getBySlug(cmsSlug);
  if (!page) return { title: "Page not found", robots: { index: false, follow: false } };

  return publicSeoToMetadata(page.seo, `/legal/${slug}`);
}

export default async function LegalSlugPage({ params }: LegalSlugPageProps) {
  const { slug } = await params;
  const cmsSlug = LEGAL_SLUG_MAP[slug] ?? slug;
  const page = await CustomPageService.getBySlug(cmsSlug);
  if (!page) {
    notFound();
  }

  return <CustomClientPageRenderer page={page} />;
}
