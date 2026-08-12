"use client";

import Link from "next/link";
import { useMemo, useState, type ReactNode } from "react";
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
const COLLAPSIBLE_GROUPS = new Set(["Bookings", "Finance", "Agency", "Travel", "Support", "Account"]);

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

function sectionContainsPath(items: PortalNavItem[], pathname: string): boolean {
  return items.some((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
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
      <div className="sticky top-[calc(var(--jp-nav-height)+1rem)] space-y-3 rounded-jp-lg border border-jp-border bg-jp-surface p-3.5 shadow-jp-sm">
        <div className="border-b border-jp-border pb-3">
          <p className="text-[0.68rem] font-semibold uppercase tracking-wide text-jp-text/70">{identityLabel}</p>
          <p className="mt-1 truncate text-jp-sm font-semibold text-jp-text">{identityValue}</p>
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
      <div className="mx-auto flex w-full max-w-[90rem] items-center justify-between px-jp-lg py-jp-sm">
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
      <h1 id={id} className="font-display text-jp-h2 font-semibold tracking-tight text-jp-text">
        {title}
      </h1>
    </div>
  );
}

export function PortalContent({ children, titleId = "portal-page-title" }: { children: ReactNode; titleId?: string }) {
  return (
    <section className="jp-app-portal__main" aria-labelledby={titleId} data-testid="portal-main">
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
    <div className="jp-portal jp-app-portal bg-jp-page" data-testid={testId}>
      {topbar}
      {drawer}
      <div className="jp-app-portal__frame xl:gap-jp-lg xl:px-jp-xl xl:py-jp-lg">
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
  return (
    <PortalGroupedNav items={items} pathname={pathname} onNavigate={onNavigate} ariaLabel={ariaLabel} />
  );
}

function PortalGroupedNav({
  items,
  pathname,
  onNavigate,
  ariaLabel,
}: {
  items: PortalNavItem[];
  pathname: string;
  onNavigate?: () => void;
  ariaLabel: string;
}) {
  const sections = useMemo(() => groupNavItems(items), [items]);
  const [manualOpen, setManualOpen] = useState<Record<string, boolean>>({});

  return (
    <nav aria-label={ariaLabel} className="space-y-2" data-testid="portal-nav">
      {sections.map((section) => {
        const group = section.group;
        const activeInGroup = sectionContainsPath(section.items, pathname);
        const collapsible = Boolean(group && COLLAPSIBLE_GROUPS.has(group) && section.items.length > 1);

        if (!group || group === "Overview" || !collapsible) {
          return (
            <div key={group ?? "all"} className="space-y-1">
              {group && group !== "Overview" ? (
                <p className="px-3 pb-0.5 text-[0.7rem] font-semibold uppercase tracking-[0.08em] text-jp-text/70">{group}</p>
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
          );
        }

        const showItems = manualOpen[group] === undefined ? activeInGroup : manualOpen[group] === true;

        return (
          <div key={group} className="space-y-1">
            <button
              type="button"
              className="flex w-full min-h-9 items-center justify-between rounded-jp-md px-3 py-1.5 text-left text-[0.7rem] font-semibold uppercase tracking-[0.08em] text-jp-text/75 hover:bg-jp-surface-muted focus-visible:shadow-jp-focus"
              aria-expanded={showItems}
              onClick={() =>
                setManualOpen((prev) => ({
                  ...prev,
                  [group]: !(prev[group] === undefined ? activeInGroup : prev[group]),
                }))
              }
            >
              <span>{group}</span>
              <span aria-hidden="true">{showItems ? "−" : "+"}</span>
            </button>
            {showItems
              ? section.items.map((item) => {
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
                })
              : null}
          </div>
        );
      })}
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

export function PortalAppFooter() {
  const year = new Date().getFullYear();
  return (
    <footer className="jp-app-footer" data-testid="portal-app-footer">
      <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-2 px-jp-lg py-4 text-jp-xs text-jp-muted sm:flex-row sm:items-center sm:justify-between xl:px-jp-xl">
        <p>© {year} JetPakistan. All rights reserved.</p>
        <nav aria-label="Portal legal" className="flex flex-wrap gap-x-4 gap-y-1">
          <Link href="/privacy" className="hover:text-jp-text focus-visible:shadow-jp-focus">
            Privacy
          </Link>
          <Link href="/terms" className="hover:text-jp-text focus-visible:shadow-jp-focus">
            Terms
          </Link>
          <Link href="/support" className="hover:text-jp-text focus-visible:shadow-jp-focus">
            Support
          </Link>
        </nav>
      </div>
    </footer>
  );
}
