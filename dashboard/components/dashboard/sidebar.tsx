"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { stripDashboardBasePath, detectPortalFromPathname } from "@/lib/portal-path";
import { dashboardHref } from "@/lib/portal-path";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardNavigationGroups, useDashboardSession } from "@/lib/session-context";
import { useDashboardPath } from "@/lib/use-dashboard-path";
import { cn } from "@/lib/utils";
import { sanitizePublicHref } from "@/lib/sanitize-public-href";
import { laravelRouteUrl } from "@/lib/laravel-route-url";
import { previewNavGroupsForPortal } from "@/lib/nav-config";
import { isPrimaryActiveNav } from "@/lib/nav-active";
import type { DashboardBranding } from "@/services/branding-service";
import type { DashboardSessionSummary } from "@/services/session-service";

type Props = {
  open: boolean;
  onClose: () => void;
  session?: DashboardSessionSummary | null;
  branding?: DashboardBranding | null;
};

type RenderGroup = {
  label: string;
  items: Array<{
    label: string;
    href: string;
    key: string;
    target?: string;
  }>;
};

const NAV_COLLAPSE_STORAGE_KEY = "jp-dashboard-nav-groups-v1";

function flattenPreviewHrefs(groups: ReturnType<typeof previewNavGroupsForPortal>): string[] {
  return groups.flatMap((group) =>
    group.items.flatMap((item) => [item.href, ...(item.children?.map((child) => child.href) ?? [])]),
  );
}

function groupContainsActive(group: RenderGroup, relativePathname: string, allHrefs: string[]): boolean {
  return group.items.some(
    (item) => item.target !== "laravel" && isPrimaryActiveNav(relativePathname, item.href, allHrefs),
  );
}

function readStoredCollapsed(): Record<string, boolean> {
  if (typeof window === "undefined") return {};
  try {
    const raw = window.sessionStorage.getItem(NAV_COLLAPSE_STORAGE_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw) as Record<string, boolean>;
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}

function writeStoredCollapsed(map: Record<string, boolean>) {
  if (typeof window === "undefined") return;
  try {
    window.sessionStorage.setItem(NAV_COLLAPSE_STORAGE_KEY, JSON.stringify(map));
  } catch {
    // ignore quota / private mode
  }
}

function Chevron({ open }: { open: boolean }) {
  return (
    <svg
      viewBox="0 0 20 20"
      fill="currentColor"
      aria-hidden="true"
      className={cn("h-4 w-4 shrink-0 text-gray-400 transition-transform duration-ui", open ? "rotate-90" : "rotate-0")}
    >
      <path
        fillRule="evenodd"
        d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
        clipRule="evenodd"
      />
    </svg>
  );
}

export function DashboardSidebar({ open, onClose, session: sessionProp, branding }: Props) {
  const pathname = usePathname();
  const homePath = useDashboardPath();
  const relativePathname = stripDashboardBasePath(pathname);
  const portal = useDashboardPortal();
  const portalFromPath = detectPortalFromPathname(pathname);
  const effectivePortal = portalFromPath ?? portal;
  const contextSession = useDashboardSession();
  const navigationGroups = useDashboardNavigationGroups();
  const session = sessionProp ?? contextSession;
  const isLive = useDashboardLiveMode();
  const useSessionNavigation = isLive && navigationGroups.length > 0;
  const previewNavGroups = previewNavGroupsForPortal(effectivePortal);

  const renderGroups: RenderGroup[] = useMemo(() => {
    if (useSessionNavigation) {
      return navigationGroups.map((group) => ({
        label: group.label,
        items: group.items.map((item) => ({
          label: item.label,
          href: item.href,
          key: item.key,
          target: item.target,
        })),
      }));
    }
    return previewNavGroups.map((group) => ({
      label: group.label,
      items: group.items.map((item) => ({
        label: item.label,
        href: item.href,
        key: `${group.label}-${item.label}`,
        target: "dashboard",
      })),
    }));
  }, [useSessionNavigation, navigationGroups, previewNavGroups]);

  const allHrefs = useMemo(
    () =>
      useSessionNavigation
        ? navigationGroups.flatMap((group) => group.items.map((item) => item.href))
        : flattenPreviewHrefs(previewNavGroups),
    [useSessionNavigation, navigationGroups, previewNavGroups],
  );

  const [collapsedManual, setCollapsedManual] = useState<Record<string, boolean>>({});
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    setCollapsedManual(readStoredCollapsed());
    setHydrated(true);
  }, []);

  const isGroupExpanded = useCallback(
    (group: RenderGroup): boolean => {
      const active = groupContainsActive(group, relativePathname, allHrefs);
      const isOverview = group.label.toLowerCase() === "overview";
      if (!hydrated) {
        return active || isOverview;
      }
      if (Object.prototype.hasOwnProperty.call(collapsedManual, group.label)) {
        return !collapsedManual[group.label];
      }
      return active || isOverview;
    },
    [relativePathname, allHrefs, collapsedManual, hydrated],
  );

  const toggleGroup = useCallback((label: string, currentlyExpanded: boolean) => {
    setCollapsedManual((prev) => {
      const next = { ...prev, [label]: currentlyExpanded };
      writeStoredCollapsed(next);
      return next;
    });
  }, []);

  const profile = session ?? {
    displayName: isLive ? "Session unavailable" : "Preview user",
    email: "—",
    initials: "??",
    roles: isLive ? [] : ["Preview"],
  };

  return (
    <>
      <div
        className={cn(
          "fixed inset-0 z-40 bg-black/40 transition-opacity duration-drawer lg:hidden",
          open ? "opacity-100" : "pointer-events-none opacity-0",
        )}
        aria-hidden={!open}
        onClick={onClose}
      />
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex w-[min(100%,280px)] flex-col bg-jp-sidebar text-white transition-transform duration-drawer motion-reduce:transition-none lg:static lg:z-auto lg:shrink-0 lg:translate-x-0",
          open ? "translate-x-0" : "-translate-x-full lg:translate-x-0",
        )}
        aria-label="Dashboard navigation"
        data-testid="dashboard-sidebar-compact"
      >
        <div className="border-b border-white/10 p-5">
          <Link href={homePath} className="block" onClick={onClose}>
            {branding?.logoUrl ? (
              <img
                src={branding.logoUrl}
                alt={branding.brandName}
                className="h-9 w-auto max-w-[180px] object-contain object-left"
                data-testid="dashboard-brand-logo"
              />
            ) : (
              <span className="font-display text-lg font-bold tracking-tight">{branding?.brandName ?? "JetPakistan"}</span>
            )}
            <span
              className="mt-2 block text-[0.65rem] font-semibold uppercase tracking-widest text-emerald-300/90"
              data-testid="dashboard-portal-label"
            >
              {effectivePortal === "staff" ? "Staff console" : "Admin console"}
            </span>
            <span className="mt-1 block text-xs uppercase tracking-widest text-emerald-400/90">
              Fly smart, fly easy
            </span>
          </Link>
          <div className="mt-4 flex items-center gap-3 rounded-xl bg-white/5 p-3">
            <span className="flex h-11 w-11 items-center justify-center rounded-full bg-jp-accent text-sm font-semibold">
              {profile.initials}
            </span>
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold">{profile.displayName}</p>
              <p className="truncate text-xs text-gray-400">{profile.roles?.[0] ?? "User"}</p>
            </div>
            <span className="ml-auto h-2.5 w-2.5 rounded-full bg-jp-accent" title="Online" />
          </div>
        </div>
        <nav className="flex-1 overflow-y-auto overflow-x-hidden p-3" data-testid="dashboard-nav-groups">
          {isLive && !useSessionNavigation ? (
            <p className="px-3 text-sm text-gray-400">Navigation unavailable until session loads.</p>
          ) : (
            renderGroups.map((group) => {
              const expanded = isGroupExpanded(group);
              const panelId = `nav-group-${group.label.toLowerCase().replace(/\s+/g, "-")}`;
              return (
                <div key={group.label} className="mb-1 last:mb-0" data-nav-group={group.label}>
                  <button
                    type="button"
                    className="flex min-h-10 w-full items-center gap-2 rounded-lg px-3 py-1.5 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-500 transition-colors hover:bg-white/5 hover:text-gray-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                    aria-expanded={expanded}
                    aria-controls={panelId}
                    onClick={() => toggleGroup(group.label, expanded)}
                    data-testid={`nav-group-toggle-${group.label.toLowerCase().replace(/\s+/g, "-")}`}
                  >
                    <Chevron open={expanded} />
                    <span className="flex-1">{group.label}</span>
                  </button>
                  <ul
                    id={panelId}
                    hidden={!expanded}
                    className={cn("mt-1 space-y-1", !expanded && "hidden")}
                    aria-hidden={!expanded}
                  >
                    {group.items.map((item) => {
                      const active =
                        item.target !== "laravel" &&
                        isPrimaryActiveNav(relativePathname, item.href, allHrefs);
                      const href =
                        item.target === "laravel"
                          ? sanitizePublicHref(item.href)
                          : dashboardHref(effectivePortal, item.href);
                      const linkClass = cn(
                        "flex min-h-10 items-center gap-2 rounded-xl px-3 py-2 text-sm transition-colors duration-ui focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent",
                        active
                          ? "bg-jp-accent font-medium text-white"
                          : "text-gray-300 hover:bg-white/10 hover:text-white",
                      );

                      return (
                        <li key={item.key}>
                          {item.target === "laravel" ? (
                            <a href={href} onClick={onClose} className={linkClass}>
                              <span className="flex-1 truncate">{item.label}</span>
                            </a>
                          ) : (
                            <Link
                              href={href}
                              onClick={onClose}
                              className={linkClass}
                              aria-current={active ? "page" : undefined}
                            >
                              <span className="flex-1 truncate">{item.label}</span>
                            </Link>
                          )}
                        </li>
                      );
                    })}
                  </ul>
                </div>
              );
            })
          )}
        </nav>
        <div className="border-t border-white/10 p-4">
          <div className="rounded-xl bg-white/5 p-4">
            <p className="text-sm font-semibold">Need help?</p>
            <p className="mt-1 text-xs text-gray-400">Contact platform support for operational assistance.</p>
            {useSessionNavigation ? (
              <a
                href={sanitizePublicHref(
                  laravelRouteUrl(
                    effectivePortal === "staff" ? "staff.support.tickets.index" : "admin.support.tickets.index",
                  ),
                )}
                onClick={onClose}
                className="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-jp-accent px-3 py-2 text-sm font-medium text-white hover:bg-jp-accent-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
              >
                Contact Support
              </a>
            ) : (
              <button
                type="button"
                className="mt-3 min-h-11 w-full rounded-xl bg-white/10 px-3 py-2 text-sm font-medium text-gray-300"
                disabled
              >
                Support unavailable
              </button>
            )}
          </div>
        </div>
      </aside>
    </>
  );
}
