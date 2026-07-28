import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import type { PublicSession } from "@/types/session";
import type { ReactNode } from "react";

type PublicShellProps = {
  children: ReactNode;
  session: PublicSession;
};

export function PublicShell({ children, session }: PublicShellProps) {
  return (
    <div className="flex min-h-screen flex-col bg-jp-page text-jp-text">
      <SiteHeader session={session} />
      <main id="main-content" className="flex-1">
        {children}
      </main>
      <SiteFooter />
    </div>
  );
}
