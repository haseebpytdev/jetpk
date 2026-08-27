import { AccountMenu } from "@/components/navigation/AccountMenu";
import { DesktopNavigation } from "@/components/navigation/DesktopNavigation";
import { MobileNavigation } from "@/components/navigation/MobileNavigation";
import { JetPakistanLogo } from "@/components/layout/JetPakistanLogo";
import { LinkButton } from "@/components/ui/LinkButton";
import { ThemeSwitch } from "@/components/theme/ThemeSwitch";
import type { PublicConfig } from "@/features/public-content/services/public-config-service";
import type { PublicSession } from "@/types/session";
import Link from "next/link";

type SiteHeaderProps = {
  session: PublicSession;
  branding?: Pick<PublicConfig, "brand_name" | "logo_url" | "header_logo_height"> | null;
};

export function SiteHeader({ session, branding = null }: SiteHeaderProps) {
  const signedIn = session.status === "authenticated";

  return (
    <header className="sticky top-0 z-40 overflow-visible border-b border-jp-border bg-jp-surface">
      <div className="mx-auto grid h-jp-nav w-full max-w-jp-container grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-jp-md overflow-visible px-jp-xl">
        <div className="flex min-w-0 items-center justify-start">
          <Link
            href="/"
            className="shrink-0 rounded-jp-md focus-visible:outline-none focus-visible:shadow-jp-focus"
            aria-label="JetPakistan home"
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

        <div className="flex items-center justify-end gap-2">
          <div className="hidden shrink-0 items-center gap-2 lg:flex">
            <ThemeSwitch />
            {signedIn ? (
              <AccountMenu session={session} />
            ) : (
              <LinkButton href="/login" variant="primary" className="jp-header-login-cta gap-1.5" data-testid="header-login-cta">
                <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.75"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  aria-hidden="true"
                >
                  <rect x="5" y="11" width="14" height="10" rx="2" />
                  <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                </svg>
                <span>Login</span>
              </LinkButton>
            )}
          </div>
          <MobileNavigation session={session} />
        </div>
      </div>
    </header>
  );
}
