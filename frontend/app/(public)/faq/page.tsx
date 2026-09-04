import type { Metadata } from "next";
import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { Breadcrumbs, FaqPageClient, FaqService, PublicPageHero, publicSeoToMetadata } from "@/features/public-content";

/**
 * Published FAQ is ISR-cached for soft-nav.
 * CMS preview lives at /faq/preview (force-dynamic).
 */
export const revalidate = 300;
export const dynamic = "force-static";

export async function generateMetadata(): Promise<Metadata> {
  const page = await FaqService.getFaqPage();
  return publicSeoToMetadata(page.seo, "/faq");
}

export default async function FaqPage() {
  const page = await FaqService.getFaqPage();

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "FAQ" }]} />
      <div className="mt-jp-xl space-y-jp-2xl">
        <PublicPageHero hero={page.hero} id="faq-page-heading" variant="support" />
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
