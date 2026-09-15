import type { ReactNode } from "react";
import type { Metadata } from "next";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService, SeoJsonLd } from "@/features/public-content";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

export async function generateMetadata(): Promise<Metadata> {
  const config = await PublicConfigService.getConfig();
  const google = config?.site_verification?.google?.trim();
  const bing = config?.site_verification?.bing?.trim();

  return {
    verification: {
      ...(google ? { google } : {}),
      ...(bing ? { other: { "msvalidate.01": bing } } : {}),
    },
  };
}

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
