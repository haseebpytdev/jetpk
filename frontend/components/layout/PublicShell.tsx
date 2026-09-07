"use client";

import type { PublicConfig } from "@/features/public-content/services/public-config-service";
import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import { AskJetPakistanChat } from "@/features/ai-assistant/components/AskJetPakistanChat";
import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import { PublicRoutePrefetch } from "@/components/navigation/PublicRoutePrefetch";
import Link from "next/link";
import {
  fetchSessionBootstrap,
  mapBootstrapToPublicSession,
} from "@/features/auth/services/session-service";
import type { PublicSession } from "@/types/session";
import { useEffect, useState, type ReactNode } from "react";

type PublicShellProps = {
  children: ReactNode;
  session: PublicSession;
  branding?: Pick<PublicConfig, "brand_name" | "logo_url" | "header_logo_height"> | null;
  hideFooter?: boolean;
  aiEnabled?: boolean;
};

type Branding = Pick<PublicConfig, "brand_name" | "logo_url" | "header_logo_height"> | null;

/**
 * Public chrome. Layouts may pass anonymous session for static soft-nav;
 * this shell upgrades session + branding from Laravel after hydration.
 */
export function PublicShell({
  children,
  session: initialSession,
  branding: initialBranding = null,
  hideFooter = false,
  aiEnabled: initialAiEnabled = false,
}: PublicShellProps) {
  const [session, setSession] = useState<PublicSession>(initialSession);
  const [branding, setBranding] = useState<Branding>(initialBranding);
  const [aiEnabled, setAiEnabled] = useState(initialAiEnabled);

  useEffect(() => {
    document.documentElement.dataset.jpHydrated = "1";
    let cancelled = false;

    void (async () => {
      try {
        const [bootstrap, config] = await Promise.all([
          fetchSessionBootstrap().catch(() => null),
          PublicConfigService.getConfig().catch(() => null),
        ]);
        if (cancelled) return;
        if (bootstrap) {
          setSession(mapBootstrapToPublicSession(bootstrap));
        }
        if (config) {
          setBranding({
            brand_name: config.brand_name,
            logo_url: config.logo_url,
            header_logo_height: config.header_logo_height,
          });
          setAiEnabled(Boolean(config.ai_assistant_enabled));
        }
      } catch {
        /* keep SSR/anonymous defaults */
      }
    })();

    return () => {
      cancelled = true;
      delete document.documentElement.dataset.jpHydrated;
    };
  }, []);

  return (
    <div className="jp-page flex min-h-screen min-w-0 flex-col overflow-x-hidden bg-jp-page text-jp-text">
      <PublicRoutePrefetch />
      <SiteHeader session={session} branding={branding} aiEnabled={aiEnabled} />
      <main id="main-content" className="jp-main flex-1">
        {children}
      </main>
      {hideFooter ? null : <SiteFooter branding={branding} />}
      <Link
        href="/support"
        data-testid="human-support-fab"
        aria-label="Human support"
        className="pointer-events-auto fixed z-40 hidden h-12 w-12 items-center justify-center rounded-full border border-white/30 bg-jp-brand text-white shadow-jp-md lg:inline-flex right-[max(0.75rem,env(safe-area-inset-right))] bottom-[max(1.25rem,env(safe-area-inset-bottom))] focus-visible:outline-none focus-visible:shadow-jp-focus"
      >
        <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" aria-hidden="true">
          <path
            d="M5 13v-1.5A7 7 0 0 1 12 4.5 7 7 0 0 1 19 11.5V13"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
          />
          <path
            d="M5 13.5A2.5 2.5 0 0 0 7.5 16h.5v-5H7.5A2.5 2.5 0 0 0 5 13.5Zm14 0A2.5 2.5 0 0 1 16.5 16H16v-5h.5A2.5 2.5 0 0 1 19 13.5Z"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinejoin="round"
          />
          <path d="M9 19c.8 1.2 1.9 1.8 3 1.8s2.2-.6 3-1.8" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
        </svg>
      </Link>
      <AskJetPakistanChat enabled={aiEnabled} />
    </div>
  );
}
