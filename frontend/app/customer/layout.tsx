import type { Metadata } from "next";
import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { requireCustomerPortalLayoutAccess } from "@/features/auth/server/customer-portal-access";
import { PortalAppFooter } from "@/features/portal";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  robots: { index: false, follow: false },
};

export default async function CustomerLayout({ children }: { children: ReactNode }) {
  const session = await requireCustomerPortalLayoutAccess();
  return (
    <PublicShell session={session} hideFooter>
      {children}
      <PortalAppFooter />
    </PublicShell>
  );
}
