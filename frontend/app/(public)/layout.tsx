import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService, SeoJsonLd } from "@/features/public-content";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

export default async function PublicGroupLayout({ children }: { children: ReactNode }) {
  const session = await getPublicSession();
  const config = await PublicConfigService.getConfig();

  return (
    <PublicShell session={session}>
      <SeoJsonLd config={config} />
      {children}
    </PublicShell>
  );
}
