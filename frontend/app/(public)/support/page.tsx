import type { Metadata } from "next";
import { PageContainer } from "@/components/layout/PageContainer";
import {
  Breadcrumbs,
  SupportContentService,
  SupportPageClient,
  fetchSupportCategories,
  publicSeoToMetadata,
} from "@/features/public-content";

export const revalidate = 300;
export const dynamic = "force-static";

export async function generateMetadata(): Promise<Metadata> {
  const content = await SupportContentService.getSupportPage();
  return publicSeoToMetadata(content.seo, "/support");
}

/**
 * Support — no SSR session (soft-nav). Authenticated portal deep-links can be
 * added client-side later; guest path must stay ISR-static.
 */
export default async function SupportPage() {
  const [content, categories] = await Promise.all([
    SupportContentService.getSupportPage(),
    fetchSupportCategories(),
  ]);

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "Support" }]} />
      <div className="mt-jp-xl">
        <SupportPageClient
          content={content}
          accountSupportHref={null}
          accountSupportLabel={null}
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
      </div>
    </PageContainer>
  );
}
