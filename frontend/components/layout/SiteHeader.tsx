"use client";

import { AccountMenu } from "@/components/navigation/AccountMenu";
import { DesktopNavigation } from "@/components/navigation/DesktopNavigation";
import { PublicFloatingActionDock } from "@/components/navigation/PublicFloatingActionDock";
import { JetPakistanLogo } from "@/components/layout/JetPakistanLogo";
import { LinkButton } from "@/components/ui/LinkButton";
import { ThemeSwitch } from "@/components/theme/ThemeSwitch";
import type { PublicConfig } from "@/features/public-content/services/public-config-service";
import type { PublicSession } from "@/types/session";
import Link from "next/link";

type SiteHeaderProps = {
  session: PublicSession;
  branding?: Pick<PublicConfig, "brand_name" | "logo_url" | "header_logo_height"> | null;
  aiEnabled?: boolean;
};

const anonymousLoginActionClass =
  "inline-flex min-h-jp-button items-center justify-center gap-1.5 rounded-jp-pill border-jp-brand-border bg-jp-brand-soft px-3 text-jp-brand shadow-none hover:border-jp-brand hover:bg-jp-brand hover:text-white";

/**
 * Desktop (lg+): conventional centered nav.
 * Below lg: logo + compact account chrome; primary nav moves to PublicFloatingActionDock
 * to prevent logo/nav collision on tablet widths.
 */
export function SiteHeader({ session, branding = null, aiEnabled = false }: SiteHeaderProps) {
  const signedIn = session.status === "authenticated";

  return (
    <>
      <header className="sticky top-0 z-40 overflow-visible border-b border-jp-border bg-jp-surface" data-testid="site-header">
        <div className="mx-auto flex h-jp-nav w-full max-w-jp-container items-center justify-between gap-jp-md overflow-visible px-jp-xl lg:grid lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
          <div className="flex min-w-0 items-center justify-start">
            <Link
              href="/"
              prefetch
              className="shrink-0 rounded-jp-md focus-visible:outline-none focus-visible:shadow-jp-focus"
              aria-label="JetPakistan home"
              data-testid="site-logo-link"
            >
              <JetPakistanLogo
                showTagline={false}
                logoUrl={branding?.logo_url}
                brandName={branding?.brand_name}
                logoHeight={branding?.header_logo_height}
              />
            </Link>
          </div>

          <DesktopNavigation session={session} className="justify-center" />

          <div className="flex shrink-0 items-center justify-end gap-2">
            <div className="hidden shrink-0 items-center gap-2 lg:flex">
              <ThemeSwitch />
              {signedIn ? (
                <AccountMenu session={session} />
              ) : (
                <>
                  <LinkButton
                    href="/register"
                    prefetch
                    variant="secondary"
                    className="inline-flex min-h-jp-button items-center justify-center rounded-jp-pill border-transparent bg-transparent px-3 text-jp-text hover:bg-jp-primary-soft"
                    data-testid="header-register-cta"
                  >
                    Register
                  </LinkButton>
                  <LinkButton
                    href="/login"
                    prefetch
                    variant="secondary"
                    className={anonymousLoginActionClass}
                    data-testid="header-login-cta"
                  >
                    <svg
                      width="15"
                      height="15"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="1.8"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      aria-hidden="true"
                    >
                      <circle cx="12" cy="8" r="3.5" />
                      <path d="M5.5 20a6.5 6.5 0 0 1 13 0" />
                    </svg>
                    <span>Login</span>
                  </LinkButton>
                </>
              )}
            </div>
            <div className="lg:hidden">
              <AccountMenu
                session={session}
                compact
                className={signedIn ? undefined : anonymousLoginActionClass}
              />
            </div>
          </div>
        </div>
      </header>
      <PublicFloatingActionDock session={session} aiEnabled={aiEnabled} />
    </>
  );
}
