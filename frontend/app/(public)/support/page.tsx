import type { Metadata } from "next";
import { PageContainer } from "@/components/layout/PageContainer";
import {
  Breadcrumbs,
  SupportContentService,
  SupportPageClient,
  fetchSupportCategories,
  publicSeoToMetadata,
} from "@/features/public-content";
import { getPublicSession } from "@/services/session";

export async function generateMetadata(): Promise<Metadata> {
  const content = await SupportContentService.getSupportPage();
  return publicSeoToMetadata(content.seo, "/support");
}

function accountSupportForSession(session: Awaited<ReturnType<typeof getPublicSession>>): {
  href: string | null;
  label: string | null;
} {
  if (session.status !== "authenticated") {
    return { href: null, label: null };
  }
  if (session.accountType === "customer") {
    return { href: "/customer/support", label: "Open my support requests" };
  }
  if (session.accountType === "agent") {
    return { href: "/agent/support", label: "Open agency support" };
  }
  if (
    session.accountType === "staff" ||
    session.portalType === "staff" ||
    session.accountType === "platform_admin" ||
    session.accountType === "admin" ||
    session.portalType === "admin"
  ) {
    return {
      href: session.dashboardUrl || "/admin/dashboard",
      label: "Open operations dashboard",
    };
  }
  return { href: session.dashboardUrl || null, label: "Open dashboard" };
}

export default async function SupportPage() {
  const [content, categories, session] = await Promise.all([
    SupportContentService.getSupportPage(),
    fetchSupportCategories(),
    getPublicSession(),
  ]);
  const accountSupport = accountSupportForSession(session);

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "Support" }]} />
      <div className="mt-jp-xl">
        <SupportPageClient
          content={content}
          accountSupportHref={accountSupport.href}
          accountSupportLabel={accountSupport.label}
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
