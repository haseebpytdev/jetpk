import { AccountMenu } from "@/components/navigation/AccountMenu";
import { CurrencySelector } from "@/components/navigation/CurrencySelector";
import { DesktopNavigation } from "@/components/navigation/DesktopNavigation";
import { MobileNavigation } from "@/components/navigation/MobileNavigation";
import { JetPakistanLogo } from "@/components/layout/JetPakistanLogo";
import { LinkButton } from "@/components/ui/LinkButton";
import { ThemeSwitch } from "@/components/theme/ThemeSwitch";
import type { PublicSession } from "@/types/session";
import Link from "next/link";

type SiteHeaderProps = {
  session: PublicSession;
};

export function SiteHeader({ session }: SiteHeaderProps) {
  return (
    <header className="sticky top-0 z-40 border-b border-jp-border bg-jp-surface/95 backdrop-blur supports-[backdrop-filter]:bg-jp-surface/90">
      <div className="mx-auto flex h-jp-nav w-full max-w-jp-container items-center justify-between gap-jp-md px-jp-xl">
        <div className="flex min-w-0 items-center gap-jp-lg">
          <Link
            href="/"
            className="shrink-0 rounded-jp-md focus-visible:outline-none focus-visible:shadow-jp-focus"
            aria-label="JetPakistan home"
          >
            <JetPakistanLogo showTagline={false} />
          </Link>
          <DesktopNavigation />
        </div>

        <div className="hidden items-center gap-jp-sm lg:flex">
          <ThemeSwitch />
          <CurrencySelector />
          <AccountMenu session={session} />
          <LinkButton href="/flights" variant="primary">
            Book Now
          </LinkButton>
        </div>

        <MobileNavigation session={session} />
      </div>
    </header>
  );
}
