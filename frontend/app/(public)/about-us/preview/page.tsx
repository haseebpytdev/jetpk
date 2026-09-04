import type { Metadata } from "next";
import { unstable_noStore as noStore } from "next/cache";
import { AboutPageContent, PublicPageService, publicSeoToMetadata } from "@/features/public-content";
import {
  cmsPreviewRequestHeaders,
  isCmsPreviewFlag,
  readCmsPreviewToken,
} from "@/features/public-content/utils/cms-preview";

export const dynamic = "force-dynamic";

type AboutUsPreviewPageProps = {
  searchParams?: Promise<Record<string, string | string[] | undefined>>;
};

export async function generateMetadata(): Promise<Metadata> {
  noStore();
  const page = await PublicPageService.getAboutPage({ preview: true });
  return publicSeoToMetadata(page.seo, "/about-us");
}

/** CMS draft preview — not on the warm soft-nav path. */
export default async function AboutUsPreviewPage({ searchParams }: AboutUsPreviewPageProps) {
  noStore();
  const params = searchParams ? await searchParams : {};
  const preview = isCmsPreviewFlag(params.jp_preview) || true;
  const previewToken = readCmsPreviewToken(params.jp_preview_token);
  const page = await PublicPageService.getAboutPage({
    preview,
    previewToken,
    headers: await cmsPreviewRequestHeaders(preview, previewToken),
  });
  return <AboutPageContent page={page} />;
}
