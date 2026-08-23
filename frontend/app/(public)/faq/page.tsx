import type { Metadata } from "next";
import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { Breadcrumbs, FaqPageClient, FaqService, PublicPageHero, publicSeoToMetadata } from "@/features/public-content";
import {
  cmsPreviewRequestHeaders,
  isCmsPreviewFlag,
  readCmsPreviewToken,
} from "@/features/public-content/utils/cms-preview";

export const dynamic = "force-dynamic";

type FaqPageProps = {
  searchParams?: Promise<Record<string, string | string[] | undefined>>;
};

export async function generateMetadata(): Promise<Metadata> {
  const page = await FaqService.getFaqPage();
  return publicSeoToMetadata(page.seo, "/faq");
}

export default async function FaqPage({ searchParams }: FaqPageProps) {
  const params = searchParams ? await searchParams : {};
  const preview = isCmsPreviewFlag(params.jp_preview);
  const previewToken = readCmsPreviewToken(params.jp_preview_token);
  const page = await FaqService.getFaqPage({
    preview,
    previewToken,
    headers: await cmsPreviewRequestHeaders(preview, previewToken),
  });

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "FAQ" }]} />
      <div className="mt-jp-xl space-y-jp-2xl">
        <PublicPageHero hero={page.hero} id="faq-page-heading" />
        <FaqPageClient categories={page.categories} />
        {page.cta ? (
          <div>
            <Link href={page.cta.href}>
              <PrimaryButton>{page.cta.label}</PrimaryButton>
            </Link>
          </div>
        ) : null}
      </div>
    </PageContainer>
  );
}
