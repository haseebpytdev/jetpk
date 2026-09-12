"use client";

import { JetPakistanLogo } from "@/components/layout/JetPakistanLogo";
import Link from "next/link";
import { useEffect, type ReactNode } from "react";

type AuthRouteLayoutShellProps = {
  children: ReactNode;
};

/**
 * Minimal chrome for `(auth)/*` routes.
 *
 * Avoids PublicShell marketing stack (AI chat, route prefetch, full nav/footer,
 * session/config bootstrap) while preserving auth page structure and soft-nav
 * hydration marker used by perf harnesses.
 */
export function AuthRouteLayoutShell({ children }: AuthRouteLayoutShellProps) {
  useEffect(() => {
    document.documentElement.dataset.jpHydrated = "1";
    return () => {
      delete document.documentElement.dataset.jpHydrated;
    };
  }, []);

  return (
    <div className="jp-page flex min-h-screen min-w-0 flex-col overflow-x-hidden bg-jp-page text-jp-text">
      <header
        className="sticky top-0 z-40 border-b border-jp-border bg-jp-surface"
        data-testid="auth-route-header"
      >
        <div className="mx-auto flex h-jp-nav w-full max-w-jp-container items-center px-jp-xl">
          <Link
            href="/"
            prefetch
            className="shrink-0 rounded-jp-md focus-visible:outline-none focus-visible:shadow-jp-focus"
            aria-label="JetPakistan home"
            data-testid="site-logo-link"
          >
            <JetPakistanLogo showTagline={false} />
          </Link>
        </div>
      </header>
      <main id="main-content" className="jp-main flex-1">
        {children}
      </main>
    </div>
  );
}
