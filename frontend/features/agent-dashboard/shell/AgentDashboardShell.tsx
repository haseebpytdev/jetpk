"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
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

  useEffect(() => {
    if (initialCapabilities) return;
    void (async () => {
      const result = await fetchAgentCapabilities();
      if (result.ok) setCapabilities(result.data);
    })();
  }, [initialCapabilities]);

  const navItems: AgentNavigationItem[] = capabilities?.navigation ?? [
    { code: "overview", label: "Overview", href: "/agent/dashboard", available: true },
    { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
    { code: "profile", label: "Profile", href: "/agent/profile", available: true },
    { code: "security", label: "Security", href: "/agent/security", available: true },
  ];

  const agencyName = capabilities?.agency.name ?? "Agent portal";
  const roleLabel = capabilities?.identity.role_label ?? "Agent";
  const displayName = capabilities?.identity.display_name ?? (session.status === "authenticated" ? session.user.displayName : "Agent");

  const nav = (
    <nav aria-label="Agent dashboard" className="space-y-1">
      {navItems
        .filter((item) => item.available)
        .map((item) => {
          const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
          return (
            <Link
              key={item.href}
              href={item.href}
              aria-current={active ? "page" : undefined}
              className={`flex min-h-10 items-center rounded-jp-md px-3 py-2 text-jp-sm font-medium focus-visible:shadow-jp-focus ${
                active ? "bg-jp-primary/10 text-jp-primary" : "text-jp-text hover:bg-jp-surface-muted"
              }`}
              onClick={() => setDrawerOpen(false)}
            >
              {item.label}
              {item.code === "notifications" && unreadNotifications > 0 ? (
                <span className="ml-auto rounded-full bg-jp-primary px-2 py-0.5 text-jp-xs text-white">{unreadNotifications}</span>
              ) : null}
            </Link>
          );
        })}
    </nav>
  );

  return (
    <div className="min-h-screen bg-jp-bg" data-testid="agent-dashboard-shell">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50">
        Skip to content
      </a>

      <header className="border-b border-jp-border bg-jp-surface lg:hidden">
        <div className="flex items-center justify-between px-4 py-3">
          <button
            type="button"
            className="rounded-jp-md border border-jp-border px-3 py-2 text-jp-sm focus-visible:shadow-jp-focus"
            aria-expanded={drawerOpen}
            aria-controls="agent-mobile-nav"
            onClick={() => setDrawerOpen((open) => !open)}
          >
            Menu
          </button>
          <span className="text-jp-sm font-semibold text-jp-text">JetPakistan</span>
          <Link href="/" className="text-jp-sm text-jp-primary">
            Home
          </Link>
        </div>
      </header>

      {drawerOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden" role="presentation">
          <button type="button" className="absolute inset-0 bg-black/40" aria-label="Close menu" onClick={() => setDrawerOpen(false)} />
          <aside id="agent-mobile-nav" className="relative z-50 h-full w-72 bg-jp-surface p-4 shadow-jp-md">
            {nav}
          </aside>
        </div>
      ) : null}

      <div className="mx-auto flex max-w-7xl gap-6 px-4 py-6 lg:px-6">
        <aside className="hidden w-60 shrink-0 lg:block" aria-label="Agent sidebar">
          <div className="sticky top-6 space-y-6 rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
            <div>
              <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">JetPakistan</p>
              <p className="mt-1 text-jp-sm font-semibold text-jp-text">{agencyName}</p>
              <p className="text-jp-xs text-jp-muted">{displayName}</p>
              <p className="text-jp-xs text-jp-muted">{roleLabel}</p>
            </div>
            {nav}
            <div className="border-t border-jp-border pt-4 text-jp-sm">
              <Link href="/" className="text-jp-primary focus-visible:shadow-jp-focus">
                Return to site
              </Link>
              <Link href="/laravel/logout" className="mt-2 block text-jp-muted focus-visible:shadow-jp-focus">
                Sign out
              </Link>
            </div>
          </div>
        </aside>

        <main id="main-content" className="min-w-0 flex-1">
          <div className="mb-6">
            <h1 className="text-jp-h2 font-bold text-jp-text">{title}</h1>
          </div>
          {children}
        </main>
      </div>
    </div>
  );
}

export function AgentDashboardErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="rounded-jp-lg border border-red-200 bg-red-50 p-6 text-center" role="alert" data-testid="agent-dashboard-error">
      <p className="text-jp-sm text-red-800">{message}</p>
      {onRetry ? (
        <button
          type="button"
          className="mt-4 rounded-jp-button border border-jp-border bg-jp-surface px-4 py-2 text-jp-sm font-semibold focus-visible:shadow-jp-focus"
          onClick={onRetry}
        >
          Try again
        </button>
      ) : null}
    </div>
  );
}

export function AgentEmptyState({ title, description, action }: { title: string; description: string; action?: React.ReactNode }) {
  return (
    <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-8 text-center" data-testid="agent-dashboard-empty">
      <h2 className="text-jp-base font-semibold text-jp-text">{title}</h2>
      <p className="mt-2 text-jp-sm text-jp-muted">{description}</p>
      {action ? <div className="mt-4">{action}</div> : null}
    </div>
  );
}

export function PermissionDeniedState({ message }: { message: string }) {
  return (
    <div className="rounded-jp-lg border border-amber-200 bg-amber-50 p-6 text-center" role="alert" data-testid="agent-permission-denied">
      <p className="text-jp-sm text-amber-900">{message}</p>
    </div>
  );
}

export function StatusBadge({ status }: { status: { label: string; code: string } }) {
  return (
    <span className="inline-flex rounded-full border border-jp-border bg-jp-surface-muted px-2 py-0.5 text-jp-xs font-medium text-jp-text">
      {status.label}
    </span>
  );
}
