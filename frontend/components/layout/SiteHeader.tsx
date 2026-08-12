import { AccountMenu } from "@/components/navigation/AccountMenu";
import { CurrencySelector } from "@/components/navigation/CurrencySelector";
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

        <div className="hidden items-center gap-jp-sm lg:flex">
          <ThemeSwitch />
          <CurrencySelector />
          {session.status === "authenticated" ? (
            <Link
              href={session.dashboardUrl || "/"}
              className="inline-flex min-h-jp-button items-center rounded-jp-button px-3 text-jp-sm font-semibold text-jp-primary transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              Dashboard
            </Link>
          ) : null}
          <AccountMenu session={session} />
          <LinkButton href="/#flight-search" variant="primary">
            Book Now
          </LinkButton>
        </div>

        <MobileNavigation session={session} />
      </div>
    </header>
  );
}
