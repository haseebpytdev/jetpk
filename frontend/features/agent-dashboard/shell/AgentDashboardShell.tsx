"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorState } from "@/components/ui/ErrorState";
import { StatusBadge as UiStatusBadge } from "@/components/ui/StatusBadge";
import {
  PortalContent,
  PortalMobileDrawer,
  PortalPageHeader,
  PortalShell,
  PortalSidebarFooter,
  PortalTopbar,
  buildPortalNav,
  type PortalNavItem,
} from "@/features/portal";
import type { AgentCapabilities, AgentNavigationItem } from "../types";
import type { PublicSession } from "@/types/session";
import { fetchAgentCapabilities } from "../services/agent-dashboard-api";

type AgentDashboardShellProps = {
  session: PublicSession;
  title: string;
  children: React.ReactNode;
  unreadNotifications?: number;
  capabilities?: AgentCapabilities | null;
};

export function AgentDashboardShell({
  session,
  title,
  children,
  unreadNotifications = 0,
  capabilities: initialCapabilities = null,
}: AgentDashboardShellProps) {
  const pathname = usePathname();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [capabilities, setCapabilities] = useState<AgentCapabilities | null>(initialCapabilities);

  const closeDrawer = () => setDrawerOpen(false);

  useEffect(() => {
    if (initialCapabilities) return;
    void (async () => {
      const result = await fetchAgentCapabilities();
      if (result.ok) setCapabilities(result.data);
    })();
  }, [initialCapabilities]);

  const navItems: PortalNavItem[] = (capabilities?.navigation ?? [
    { code: "overview", label: "Overview", href: "/agent/dashboard", available: true },
    { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
    { code: "profile", label: "Profile", href: "/agent/profile", available: true },
    { code: "security", label: "Security", href: "/agent/security", available: true },
  ] as AgentNavigationItem[])
    .filter((item) => item.available)
    .map((item) => ({
      href: item.href,
      label: item.label,
      code: item.code,
      badge: item.code === "notifications" ? unreadNotifications : undefined,
    }));

  const nav = buildPortalNav(navItems, pathname, closeDrawer, "Agent dashboard");

  const agencyName = capabilities?.agency.name ?? "Agent portal";
  const roleLabel = capabilities?.identity.role_label ?? "Agent";
  const displayName =
    capabilities?.identity.display_name ??
    (session.status === "authenticated" ? session.user.displayName : "Agent");

  const identityBlock = (
    <div>
      <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Agency</p>
      <p className="mt-1 text-jp-sm font-semibold text-jp-text">{agencyName}</p>
      <p className="truncate text-jp-xs text-jp-muted">{displayName}</p>
      <p className="text-jp-xs text-jp-muted">{roleLabel}</p>
    </div>
  );

  const sidebar = (
    <aside className="hidden w-jp-sidebar shrink-0 lg:block" aria-label="Agent sidebar" data-testid="portal-sidebar">
      <div className="sticky top-[calc(var(--jp-nav-height)+1rem)] space-y-6 rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-sm">
        {identityBlock}
        {nav}
        <div className="border-t border-jp-border pt-4 text-jp-sm">
          <PortalSidebarFooter />
        </div>
      </div>
    </aside>
  );

  return (
    <PortalShell
      testId="agent-dashboard-shell"
      topbar={
        <PortalTopbar
          title={title}
          drawerOpen={drawerOpen}
          onToggleDrawer={() => setDrawerOpen((open) => !open)}
          drawerControlsId="portal-mobile-nav"
        />
      }
      drawer={
        <PortalMobileDrawer
          open={drawerOpen}
          onClose={closeDrawer}
          title="Dashboard"
          nav={
            <>
              {identityBlock}
              {nav}
            </>
          }
          footer={<PortalSidebarFooter />}
        />
      }
      sidebar={sidebar}
      content={
        <PortalContent titleId="agent-page-title">
          <PortalPageHeader title={title} id="agent-page-title" />
          {children}
        </PortalContent>
      }
    />
  );
}

export function AgentDashboardErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return <ErrorState message={message} onRetry={onRetry} testId="agent-dashboard-error" className="text-left" />;
}

export function AgentEmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action?: React.ReactNode;
}) {
  return <EmptyState title={title} description={description} action={action} testId="agent-dashboard-empty" />;
}

export function PermissionDeniedState({ message }: { message: string }) {
  return (
    <div
      className="rounded-jp-lg border border-jp-warning bg-jp-warning-soft p-6 text-center"
      role="alert"
      data-testid="agent-permission-denied"
    >
      <p className="text-jp-sm text-jp-warning">{message}</p>
    </div>
  );
}

export function StatusBadge({ status }: { status: { label: string; code: string } }) {
  return <UiStatusBadge data-testid={`status-${status.code}`}>{status.label}</UiStatusBadge>;
}
