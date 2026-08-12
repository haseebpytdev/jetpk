"use client";

import { AccountMenu } from "@/components/navigation/AccountMenu";
import { CurrencySelector } from "@/components/navigation/CurrencySelector";
import { ThemeSwitch } from "@/components/theme/ThemeSwitch";
import { Badge } from "@/components/ui/Badge";
import { IconButton } from "@/components/ui/IconButton";
import { useBodyScrollLock } from "@/lib/hooks/use-body-scroll-lock";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useFocusTrap } from "@/lib/hooks/use-focus-trap";
import { primaryNavigationForSession } from "@/lib/navigation";
import { cn } from "@/lib/cn";
import Link from "next/link";
import type { PublicSession } from "@/types/session";
import { useCallback, useRef, useState } from "react";
import type { NavItem } from "@/types/navigation";

type MobileNavigationProps = {
  session: PublicSession;
  className?: string;
};

export function MobileNavigation({ session, className }: MobileNavigationProps) {
  const [open, setOpen] = useState(false);
  const panelRef = useRef<HTMLDivElement>(null);
  const menuButtonRef = useRef<HTMLButtonElement>(null);

  const closeMenu = useCallback(() => {
    setOpen(false);
    menuButtonRef.current?.focus();
  }, []);

  useBodyScrollLock(open);
  useEscapeKey(open, closeMenu);
  useFocusTrap(open, panelRef);

  return (
    <div className={cn("relative overflow-visible lg:hidden", className)}>
      <div className="flex items-center gap-2">
        <AccountMenu session={session} compact />
        <IconButton
          ref={menuButtonRef}
          label={open ? "Close navigation menu" : "Open navigation menu"}
          aria-expanded={open}
          aria-controls="mobile-navigation-panel"
          onClick={() => setOpen((value) => !value)}
        >
          {open ? <CloseIcon /> : <MenuIcon />}
        </IconButton>
      </div>

      {open ? (
        <>
          <button
            type="button"
            aria-label="Close navigation menu"
            className="fixed inset-0 z-40 bg-jp-overlay"
            onClick={closeMenu}
          />
          <div
            ref={panelRef}
            id="mobile-navigation-panel"
            role="dialog"
            aria-modal="true"
            aria-label="Mobile navigation"
            className="fixed inset-y-0 right-0 z-50 flex w-[min(100vw-3rem,22rem)] flex-col border-l border-jp-border bg-jp-surface shadow-jp-md transition-transform duration-drawer motion-reduce:transition-none"
          >
            <div className="flex items-center justify-between border-b border-jp-border px-4 py-4">
              <p className="text-jp-sm font-semibold text-jp-text">Menu</p>
              <IconButton label="Close navigation menu" onClick={closeMenu}>
                <CloseIcon />
              </IconButton>
            </div>

            <nav aria-label="Mobile primary" className="min-h-0 flex-1 overflow-y-auto px-4 py-4">
              <ul className="space-y-1">
                {primaryNavigationForSession(session).map((item) => (
                  <MobileNavItem key={item.label} item={item} onNavigate={closeMenu} />
                ))}
              </ul>
            </nav>

            <div className="space-y-3 border-t border-jp-border px-4 py-4">
              <div className="flex items-center gap-2">
                <ThemeSwitch className="!min-h-9 !gap-1.5 !px-2 !py-1.5" />
                <CurrencySelector compact className="min-w-0 flex-1" />
              </div>
              {session.status === "anonymous" ? null : (
                <Link
                  href="/#flight-search"
                  className="block text-jp-sm font-semibold text-jp-text focus-visible:shadow-jp-focus"
                  onClick={closeMenu}
                >
                  Search flights
                </Link>
              )}
            </div>
          </div>
        </>
      ) : null}
    </div>
  );
}

function MobileNavItem({
  item,
  onNavigate,
}: {
  item: NavItem;
  onNavigate: () => void;
}) {
  if (item.type === "link") {
    return (
      <li>
        <a
          href={item.href}
          onClick={onNavigate}
          className="flex items-center justify-between rounded-jp-md px-3 py-3 text-jp-body font-medium text-jp-text hover:bg-jp-brand-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          <span>{item.label}</span>
          {item.badge ? <Badge variant="new">{item.badge}</Badge> : null}
        </a>
      </li>
    );
  }

  return (
    <li className="rounded-jp-md border border-jp-border">
      <p className="px-3 pt-3 text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
        {item.label}
      </p>
      <ul className="px-1 pb-2">
        {item.items.map((link) => (
          <li key={link.href}>
            <a
              href={link.href}
              onClick={onNavigate}
              className="block rounded-jp-sm px-3 py-2 text-jp-sm text-jp-text hover:bg-jp-brand-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              {link.label}
            </a>
          </li>
        ))}
      </ul>
    </li>
  );
}

function MenuIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" aria-hidden="true">
      <path d="M4 7H20M4 12H20M4 17H20" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

function CloseIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" aria-hidden="true">
      <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}
