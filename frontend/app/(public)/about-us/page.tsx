import type { Metadata } from "next";
import { unstable_noStore as noStore } from "next/cache";
import { AboutPageContent, PublicPageService, publicSeoToMetadata } from "@/features/public-content";
import {
  cmsPreviewRequestHeaders,
  isCmsPreviewFlag,
  readCmsPreviewToken,
} from "@/features/public-content/utils/cms-preview";

/** Published About uses short ISR; preview requests call noStore() below. */
export const revalidate = 60;

type AboutUsPageProps = {
  searchParams?: Promise<Record<string, string | string[] | undefined>>;
};

export async function generateMetadata(): Promise<Metadata> {
  const page = await PublicPageService.getAboutPage();
  return publicSeoToMetadata(page.seo, "/about-us");
}

export default async function AboutUsPage({ searchParams }: AboutUsPageProps) {
  const params = searchParams ? await searchParams : {};
  const preview = isCmsPreviewFlag(params.jp_preview);
  const previewToken = readCmsPreviewToken(params.jp_preview_token);
  if (preview) {
    noStore();
  }
  const page = await PublicPageService.getAboutPage({
    preview,
    previewToken,
    headers: await cmsPreviewRequestHeaders(preview, previewToken),
  });
  return <AboutPageContent page={page} />;
}
