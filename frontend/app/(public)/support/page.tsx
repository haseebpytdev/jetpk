import type { Metadata } from "next";
import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import {
  Breadcrumbs,
  PublicPageHero,
  SupportContentService,
  SupportPageClient,
  fetchSupportCategories,
  publicSeoToMetadata,
} from "@/features/public-content";

export async function generateMetadata(): Promise<Metadata> {
  const content = await SupportContentService.getSupportPage();
  return publicSeoToMetadata(content.seo, "/support");
}

export default async function SupportPage() {
  const [content, categories] = await Promise.all([
    SupportContentService.getSupportPage(),
    fetchSupportCategories(),
  ]);

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "Support" }]} />
      <div className="mt-jp-xl space-y-jp-2xl">
        <PublicPageHero hero={content.hero} id="support-page-heading" />
        <SupportPageClient
          content={content}
          categories={
            categories.length
              ? categories
              : [
                  { value: "booking", label: "Booking" },
                  { value: "payment", label: "Payment" },
                  { value: "technical", label: "Technical" },
                  { value: "other", label: "Other" },
                ]
          }
        />
        <div className="rounded-jp-lg border border-jp-border bg-jp-page p-jp-lg">
          <h2 className="text-jp-md font-semibold text-jp-text">Need to speak with us?</h2>
          <p className="mt-2 text-jp-sm text-jp-muted">
            Call, WhatsApp, or email using the verified JetPakistan contact details above.
          </p>
          <div className="mt-4">
            <Link href="/contact">
              <PrimaryButton>Contact page</PrimaryButton>
            </Link>
          </div>
        </div>
      </div>
    </PageContainer>
  );
}
