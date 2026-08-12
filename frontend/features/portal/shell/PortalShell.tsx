"use client";

import Link from "next/link";
import { useState, type ReactNode } from "react";
import { IconButton } from "@/components/ui/IconButton";
import { logout } from "@/features/auth/services/auth-service";
import { useBodyScrollLock } from "@/lib/hooks/use-body-scroll-lock";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";

export type PortalNavItem = {
  href: string;
  label: string;
  code?: string;
  badge?: number;
  group?: string;
};

const GROUP_ORDER = ["Overview", "Bookings", "Finance", "Agency", "Travel", "Support", "Account"] as const;

function groupNavItems(items: PortalNavItem[]): Array<{ group: string | null; items: PortalNavItem[] }> {
  const hasGroups = items.some((item) => Boolean(item.group));
  if (!hasGroups) {
    return [{ group: null, items }];
  }

  const buckets = new Map<string, PortalNavItem[]>();
  for (const item of items) {
    const key = item.group ?? "Other";
    const list = buckets.get(key) ?? [];
    list.push(item);
    buckets.set(key, list);
  }

  const ordered: Array<{ group: string | null; items: PortalNavItem[] }> = [];
  for (const group of GROUP_ORDER) {
    const list = buckets.get(group);
    if (list?.length) {
      ordered.push({ group, items: list });
      buckets.delete(group);
    }
  }
  for (const [group, list] of buckets) {
    ordered.push({ group, items: list });
  }
  return ordered;
}

type PortalMobileDrawerProps = {
  open: boolean;
  onClose: () => void;
  title: string;
  nav: ReactNode;
  footer?: ReactNode;
  testId?: string;
};

export function PortalMobileDrawer({
  open,
  onClose,
  title,
  nav,
  footer,
  testId = "portal-mobile-drawer",
}: PortalMobileDrawerProps) {
  useBodyScrollLock(open);
  useEscapeKey(open, onClose);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-40 lg:hidden" role="presentation">
      <button type="button" className="absolute inset-0 bg-jp-overlay" aria-label="Close navigation menu" onClick={onClose} />
      <aside
        id="portal-mobile-nav"
        className="relative z-50 h-full w-72 border-r border-jp-border bg-jp-surface p-4 shadow-jp-md"
        role="dialog"
        aria-modal="true"
        aria-label="Dashboard navigation"
        data-testid={testId}
      >
        <div className="mb-4 flex items-center justify-between">
          <p className="text-jp-sm font-semibold text-jp-text">{title}</p>
          <IconButton label="Close navigation menu" onClick={onClose}>
            <span aria-hidden="true">×</span>
          </IconButton>
        </div>
        {nav}
        {footer ? <div className="mt-6 border-t border-jp-border pt-4">{footer}</div> : null}
      </aside>
    </div>
  );
}

type PortalSidebarProps = {
  identityLabel: string;
  identityValue: string;
  nav: ReactNode;
  footer?: ReactNode;
  testId?: string;
};

export function PortalSidebar({ identityLabel, identityValue, nav, footer, testId = "portal-sidebar" }: PortalSidebarProps) {
  return (
    <aside className="hidden w-[13.5rem] shrink-0 xl:w-jp-sidebar lg:block" aria-label="Dashboard sidebar" data-testid={testId}>
      <div className="sticky top-[calc(var(--jp-nav-height)+1rem)] space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
        <div>
          <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">{identityLabel}</p>
          <p className="mt-1 truncate text-jp-sm font-medium text-jp-text">{identityValue}</p>
        </div>
        {nav}
        {footer ? <div className="border-t border-jp-border pt-3 text-jp-sm">{footer}</div> : null}
      </div>
    </aside>
  );
}

type PortalTopbarProps = {
  title: string;
  drawerOpen: boolean;
  onToggleDrawer: () => void;
  drawerControlsId?: string;
};

export function PortalTopbar({ title, drawerOpen, onToggleDrawer, drawerControlsId = "portal-mobile-nav" }: PortalTopbarProps) {
  return (
    <div className="border-b border-jp-border bg-jp-surface-muted lg:hidden" data-testid="portal-topbar">
      <div className="mx-auto flex max-w-jp-container items-center justify-between px-jp-xl py-jp-sm">
        <button
          type="button"
          className="rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2 text-jp-sm font-medium text-jp-text focus-visible:shadow-jp-focus"
          aria-expanded={drawerOpen}
          aria-controls={drawerControlsId}
          onClick={onToggleDrawer}
        >
          Dashboard menu
        </button>
        <span className="truncate text-jp-sm font-semibold text-jp-text">{title}</span>
      </div>
    </div>
  );
}

type PortalPageHeaderProps = {
  title: string;
  id?: string;
};

export function PortalPageHeader({ title, id = "portal-page-title" }: PortalPageHeaderProps) {
  return (
    <div className="mb-jp-lg hidden lg:block">
      <h1 id={id} className="font-display text-jp-h2 font-bold text-jp-text">
        {title}
      </h1>
    </div>
  );
}

export function PortalContent({ children, titleId = "portal-page-title" }: { children: ReactNode; titleId?: string }) {
  return (
    <section className="min-w-0 flex-1" aria-labelledby={titleId}>
      {children}
    </section>
  );
}

export function PortalShell({
  testId,
  topbar,
  drawer,
  sidebar,
  content,
}: {
  testId: string;
  topbar: ReactNode;
  drawer: ReactNode;
  sidebar: ReactNode;
  content: ReactNode;
}) {
  return (
    <div className="jp-portal bg-jp-page" data-testid={testId}>
      {topbar}
      {drawer}
      <div className="mx-auto flex w-full max-w-jp-container gap-jp-md px-jp-lg py-jp-md xl:gap-jp-lg xl:px-jp-xl xl:py-jp-lg">
        {sidebar}
        {content}
      </div>
    </div>
  );
}

export function buildPortalNav(
  items: PortalNavItem[],
  pathname: string,
  onNavigate?: () => void,
  ariaLabel = "Dashboard navigation",
) {
  const sections = groupNavItems(items);

  return (
    <nav aria-label={ariaLabel} className="space-y-4" data-testid="portal-nav">
      {sections.map((section) => (
        <div key={section.group ?? "all"} className="space-y-1">
          {section.group && section.group !== "Overview" ? (
            <p className="px-3 text-[0.68rem] font-semibold uppercase tracking-[0.08em] text-jp-muted">
              {section.group}
            </p>
          ) : null}
          {section.items.map((item) => {
            const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
            return (
              <Link
                key={item.href}
                href={item.href}
                aria-current={active ? "page" : undefined}
                className={`flex min-h-9 items-center rounded-jp-md px-3 py-1.5 text-jp-sm font-medium focus-visible:shadow-jp-focus ${
                  active ? "bg-jp-brand-soft text-jp-brand" : "text-jp-text hover:bg-jp-surface-muted"
                }`}
                onClick={onNavigate}
              >
                {item.label}
                {item.badge && item.badge > 0 ? (
                  <span className="ml-auto rounded-full bg-jp-brand px-2 py-0.5 text-jp-xs text-white">{item.badge}</span>
                ) : null}
              </Link>
            );
          })}
        </div>
      ))}
    </nav>
  );
}

export function PortalSidebarFooter() {
  const [loggingOut, setLoggingOut] = useState(false);
  const [logoutError, setLogoutError] = useState("");

  async function handleLogout() {
    if (loggingOut) return;

    setLoggingOut(true);
    setLogoutError("");

    const result = await logout();

    if (!result.ok) {
      setLoggingOut(false);
      setLogoutError(result.message);
      return;
    }

    window.location.assign(result.redirect);
  }

  return (
    <>
      <Link href="/" className="text-jp-brand focus-visible:shadow-jp-focus">
        Return to site
      </Link>
      <button
        type="button"
        onClick={handleLogout}
        disabled={loggingOut}
        className="mt-2 block text-left text-jp-muted focus-visible:shadow-jp-focus disabled:cursor-not-allowed disabled:opacity-60"
      >
        {loggingOut ? "Signing out…" : "Sign out"}
      </button>
      {logoutError ? (
        <p role="alert" className="mt-2 text-jp-xs text-jp-danger">
          {logoutError}
        </p>
      ) : null}
    </>
  );
}
