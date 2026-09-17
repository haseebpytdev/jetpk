import type { ReactNode } from "react";
import type { Metadata } from "next";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService, SeoJsonLd } from "@/features/public-content";
import type { PublicSession } from "@/types/session";

/**
 * Soft-nav / ISR: do not force-dynamic the whole public tree.
 * PublicShell upgrades anonymous SSR session from Laravel after hydration.
 */
export const revalidate = 60;

const ANONYMOUS_SESSION: PublicSession = { status: "anonymous" };

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
  const config = await PublicConfigService.getConfig();

  return (
    <PublicShell session={ANONYMOUS_SESSION}>
      <SeoJsonLd config={config} />
      {children}
    </PublicShell>
  );
}
