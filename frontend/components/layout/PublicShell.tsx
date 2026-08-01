import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import type { PublicSession } from "@/types/session";
import type { ReactNode } from "react";

type PublicShellProps = {
  children: ReactNode;
  session: PublicSession;
  /** Hide public footer for focused flows (e.g. none by default) */
  hideFooter?: boolean;
};

export function PublicShell({ children, session, hideFooter = false }: PublicShellProps) {
  return (
    <div className="jp-page flex min-h-screen flex-col bg-jp-page text-jp-text">
      <SiteHeader session={session} />
      <main id="main-content" className="jp-main flex-1">
        {children}
      </main>
      {hideFooter ? null : <SiteFooter />}
    </div>
  );
}
