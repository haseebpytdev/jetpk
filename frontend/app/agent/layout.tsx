import type { Metadata } from "next";
import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { requireAgentPortalLayoutAccess } from "@/features/auth/server/agent-portal-access";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  robots: { index: false, follow: false },
};

export default async function AgentLayout({ children }: { children: ReactNode }) {
  const session = await requireAgentPortalLayoutAccess();
  return <PublicShell session={session}>{children}</PublicShell>;
}
