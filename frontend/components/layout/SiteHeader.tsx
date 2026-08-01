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
    <header className="sticky top-0 z-40 border-b border-jp-border/80 bg-jp-surface/98 backdrop-blur supports-[backdrop-filter]:bg-jp-surface/95">
      <div className="mx-auto flex h-jp-nav w-full max-w-jp-container items-center justify-between gap-jp-md px-jp-xl lg:px-20">
        <div className="flex min-w-0 flex-1 items-center gap-jp-lg">
          <Link
            href="/"
            className="shrink-0 rounded-jp-md focus-visible:outline-none focus-visible:shadow-jp-focus"
            aria-label="JetPakistan home"
          >
            <JetPakistanLogo showTagline />
          </Link>
          <DesktopNavigation />
        </div>

        <div className="hidden items-center gap-jp-sm lg:flex">
          <CurrencySelector />
          <AccountMenu session={session} />
          <ThemeSwitch />
          <LinkButton href="/flights" variant="secondary" className="shrink-0 border-jp-primary text-jp-primary hover:bg-jp-primary-soft">
            Book Now
          </LinkButton>
        </div>

        <MobileNavigation session={session} />
      </div>
    </header>
  );
}
