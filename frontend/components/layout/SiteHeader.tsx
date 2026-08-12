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
      <div className="mx-auto flex h-jp-nav w-full max-w-jp-container items-center justify-between gap-jp-md overflow-visible px-jp-xl">
        <div className="flex min-w-0 items-center gap-jp-lg">
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
          <DesktopNavigation session={session} />
        </div>

        <div className="hidden shrink-0 items-center gap-2 lg:flex">
          <ThemeSwitch />
          {signedIn ? (
            <AccountMenu session={session} />
          ) : (
            <LinkButton
              href="/login"
              variant="primary"
              className="jp-header-login-cta whitespace-nowrap !rounded-jp-button !bg-gradient-to-br !from-[#1f8f55] !via-[#187a48] !to-[#14663c] !px-4 !shadow-none hover:!from-[#22985b] hover:!via-[#1a8550] hover:!to-[#167242] active:!from-[#14663c] active:!to-[#0f5230]"
              data-testid="header-login-cta"
            >
              Login
            </LinkButton>
          )}
        </div>

        <MobileNavigation session={session} />
      </div>
    </header>
  );
}
