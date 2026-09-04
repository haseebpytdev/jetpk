"use client";

import type { PublicConfig } from "@/features/public-content/services/public-config-service";
import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import { AskJetPakistanChat } from "@/features/ai-assistant/components/AskJetPakistanChat";
import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import { PublicRoutePrefetch } from "@/components/navigation/PublicRoutePrefetch";
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
  /** Hide public footer for focused flows (e.g. none by default) */
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
      <AskJetPakistanChat enabled={aiEnabled} />
    </div>
  );
}
