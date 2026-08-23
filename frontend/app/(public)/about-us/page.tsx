import type { Metadata } from "next";
import { AboutPageContent, PublicPageService, publicSeoToMetadata } from "@/features/public-content";
import {
  cmsPreviewRequestHeaders,
  isCmsPreviewFlag,
  readCmsPreviewToken,
} from "@/features/public-content/utils/cms-preview";

export const dynamic = "force-dynamic";
/** Explicit CMS freshness: bare /about-us must never reuse a full-route payload. */
export const revalidate = 0;
export const fetchCache = "force-no-store";

type AboutUsPageProps = {
  searchParams?: Promise<Record<string, string | string[] | undefined>>;
};

export async function generateMetadata(): Promise<Metadata> {
  // Segment fetchCache + no-store managed-page fetch apply here (SEO must track publish).
  const page = await PublicPageService.getAboutPage();
  return publicSeoToMetadata(page.seo, "/about-us");
}

export default async function AboutUsPage({ searchParams }: AboutUsPageProps) {
  const params = searchParams ? await searchParams : {};
  const preview = isCmsPreviewFlag(params.jp_preview);
  const previewToken = readCmsPreviewToken(params.jp_preview_token);
  const page = await PublicPageService.getAboutPage({
    preview,
    previewToken,
    headers: await cmsPreviewRequestHeaders(preview, previewToken),
  });
  return <AboutPageContent page={page} />;
}
