import type { Metadata } from "next";
import { AboutPageContent, PublicPageService, publicSeoToMetadata } from "@/features/public-content";
import {
  cmsPreviewRequestHeaders,
  isCmsPreviewFlag,
  readCmsPreviewToken,
} from "@/features/public-content/utils/cms-preview";

export const dynamic = "force-dynamic";

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
  const page = await PublicPageService.getAboutPage({
    preview,
    previewToken,
    headers: await cmsPreviewRequestHeaders(preview, previewToken),
  });
  return <AboutPageContent page={page} />;
}
