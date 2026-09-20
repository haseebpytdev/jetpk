import type { ReactNode } from "react";
import type { Metadata } from "next";
import { Suspense } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService, SeoJsonLd } from "@/features/public-content";
import { resolveFaviconUrl } from "@/lib/branding/resolve-favicon";
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
  const favicon = resolveFaviconUrl(config?.favicon_url);

  return {
    icons: {
      icon: [{ url: favicon }],
      shortcut: [{ url: favicon }],
    },
    verification: {
      ...(google ? { google } : {}),
      ...(bing ? { other: { "msvalidate.01": bing } } : {}),
    },
  };
}

async function PublicLayoutBody({ children }: { children: ReactNode }) {
  const config = await PublicConfigService.getConfig();
  const branding = config
    ? {
        brand_name: config.brand_name,
        logo_url: config.logo_url,
        header_logo_height: config.header_logo_height,
      }
    : null;

  return (
    <PublicShell
      session={ANONYMOUS_SESSION}
      branding={branding}
      aiEnabled={Boolean(config?.ai_assistant_enabled)}
    >
      <SeoJsonLd config={config} />
      {children}
    </PublicShell>
  );
}

/**
 * Soft-nav: Suspense the config-bound shell so home→(public) URL can commit
 * while PublicConfigService resolves (segment loading.tsx covers the gap).
 */
export default function PublicGroupLayout({ children }: { children: ReactNode }) {
  return (
    <Suspense
      fallback={
        <PublicShell session={ANONYMOUS_SESSION} branding={null} aiEnabled={false}>
          <div className="mx-auto w-full max-w-jp-container px-jp-xl py-jp-4xl">
            <div className="min-h-[16rem] animate-pulse rounded-jp-card border border-jp-border bg-jp-surface-muted" />
          </div>
        </PublicShell>
      }
    >
      <PublicLayoutBody>{children}</PublicLayoutBody>
    </Suspense>
  );
}
