"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
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
import type { DashboardBranding } from "@/services/branding-service";
import type { DashboardSessionSummary } from "@/services/session-service";

type Props = {
  open: boolean;
  onClose: () => void;
  session?: DashboardSessionSummary | null;
  branding?: DashboardBranding | null;
};

function isActive(pathname: string, href: string): boolean {
  if (href === "/") {
    return pathname === "/" || pathname === "";
  }
  const base = href.split("?")[0];
  return pathname === base || pathname.startsWith(`${base}/`);
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
        <nav className="flex-1 overflow-y-auto p-3">
          {useSessionNavigation ? (
            navigationGroups.map((group) => (
              <div key={group.label} className="mb-4 last:mb-0">
                <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-500">
                  {group.label}
                </p>
                <ul className="space-y-1">
                  {group.items.map((item) => {
                    const active = item.target !== "laravel" && isActive(relativePathname, item.href);
                    const href =
                      item.target === "laravel"
                        ? sanitizePublicHref(item.href)
                        : dashboardHref(portal, item.href);
                    const linkClass = cn(
                      "flex min-h-11 items-center gap-2 rounded-xl px-3 py-2 text-sm transition-colors duration-ui focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent",
                      active
                        ? "bg-jp-accent font-medium text-white"
                        : "text-gray-300 hover:bg-white/10 hover:text-white",
                    );

                    return (
                      <li key={item.key}>
                        {item.target === "laravel" ? (
                          <a href={href} onClick={onClose} className={linkClass}>
                            <span className="flex-1">{item.label}</span>
                          </a>
                        ) : (
                          <Link
                            href={href}
                            onClick={onClose}
                            className={linkClass}
                            aria-current={active ? "page" : undefined}
                          >
                            <span className="flex-1">{item.label}</span>
                          </Link>
                        )}
                      </li>
                    );
                  })}
                </ul>
              </div>
            ))
          ) : isLive ? (
            <p className="px-3 text-sm text-gray-400">Navigation unavailable until session loads.</p>
          ) : (
            previewNavGroups.map((group) => (
              <div key={group.label} className="mb-4 last:mb-0">
                <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-500">
                  {group.label}
                </p>
                <ul className="space-y-1">
                  {group.items.map((item) => {
                    const active = isActive(relativePathname, item.href);
                    const href = dashboardHref(portal, item.href);
                    return (
                      <li key={`${group.label}-${item.label}`}>
                        <Link
                          href={href}
                          onClick={onClose}
                          className={cn(
                            "flex min-h-11 items-center gap-2 rounded-xl px-3 py-2 text-sm transition-colors duration-ui focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent",
                            active
                              ? "bg-jp-accent font-medium text-white"
                              : "text-gray-300 hover:bg-white/10 hover:text-white",
                          )}
                          aria-current={active ? "page" : undefined}
                        >
                          <span className="flex-1">{item.label}</span>
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              </div>
            ))
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
