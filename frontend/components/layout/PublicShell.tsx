import type { PublicConfig } from "@/features/public-content/services/public-config-service";
import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import type { PublicSession } from "@/types/session";
import type { ReactNode } from "react";

type PublicShellProps = {
  children: ReactNode;
  session: PublicSession;
  branding?: Pick<PublicConfig, "brand_name" | "logo_url" | "header_logo_height"> | null;
  /** Hide public footer for focused flows (e.g. none by default) */
  hideFooter?: boolean;
};

export function PublicShell({ children, session, branding = null, hideFooter = false }: PublicShellProps) {
  return (
    <div className="jp-page flex min-h-screen min-w-0 flex-col overflow-x-hidden bg-jp-page text-jp-text">
      <SiteHeader session={session} branding={branding} />
      <main id="main-content" className="jp-main flex-1">
        {children}
      </main>
      {hideFooter ? null : <SiteFooter branding={branding} />}
    </div>
  );
}
