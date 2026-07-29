"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { ErrorState } from "@/components/ui/ErrorState";
import { EmptyState } from "@/components/ui/EmptyState";
import { StatusBadge as UiStatusBadge } from "@/components/ui/StatusBadge";
import { IconButton } from "@/components/ui/IconButton";
import { useBodyScrollLock } from "@/lib/hooks/use-body-scroll-lock";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import type { PublicSession } from "@/types/session";

const NAV_ITEMS = [
  { href: "/customer/dashboard", label: "Overview" },
  { href: "/customer/bookings", label: "My Bookings" },
  { href: "/customer/payments", label: "Payments" },
  { href: "/customer/invoices", label: "Invoices" },
  { href: "/customer/profile", label: "Profile" },
  { href: "/customer/security", label: "Security" },
  { href: "/customer/support", label: "Support" },
  { href: "/customer/notifications", label: "Notifications" },
] as const;

type CustomerDashboardShellProps = {
  session: PublicSession;
  title: string;
  children: React.ReactNode;
  unreadNotifications?: number;
};

export function CustomerDashboardShell({
  session,
  title,
  children,
  unreadNotifications = 0,
}: CustomerDashboardShellProps) {
  const pathname = usePathname();
  const [drawerOpen, setDrawerOpen] = useState(false);

  const closeDrawer = () => setDrawerOpen(false);
  useBodyScrollLock(drawerOpen);
  useEscapeKey(drawerOpen, closeDrawer);

  const nav = (
    <nav aria-label="Customer dashboard" className="space-y-1">
      {NAV_ITEMS.map((item) => {
        const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
        return (
          <Link
            key={item.href}
            href={item.href}
            aria-current={active ? "page" : undefined}
            className={`flex min-h-10 items-center rounded-jp-md px-3 py-2 text-jp-sm font-medium focus-visible:shadow-jp-focus ${
              active ? "bg-jp-brand-soft text-jp-brand" : "text-jp-text hover:bg-jp-surface-muted"
            }`}
            onClick={closeDrawer}
          >
            {item.label}
            {item.href === "/customer/notifications" && unreadNotifications > 0 ? (
              <UiStatusBadge variant="brand" className="ml-auto">
                {unreadNotifications}
              </UiStatusBadge>
            ) : null}
          </Link>
        );
      })}
    </nav>
  );

  return (
    <div className="bg-jp-page" data-testid="customer-dashboard-shell">
      <div className="border-b border-jp-border bg-jp-surface-muted lg:hidden">
        <div className="mx-auto flex max-w-jp-container items-center justify-between px-jp-xl py-jp-sm">
          <button
            type="button"
            className="rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2 text-jp-sm font-medium text-jp-text focus-visible:shadow-jp-focus"
            aria-expanded={drawerOpen}
            aria-controls="customer-mobile-nav"
            onClick={() => setDrawerOpen((open) => !open)}
          >
            Dashboard menu
          </button>
          <span className="text-jp-sm font-semibold text-jp-text">{title}</span>
        </div>
      </div>

      {drawerOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden" role="presentation">
          <button
            type="button"
            className="absolute inset-0 bg-jp-overlay"
            aria-label="Close dashboard menu"
            onClick={closeDrawer}
          />
          <aside
            id="customer-mobile-nav"
            className="relative z-50 h-full w-72 border-r border-jp-border bg-jp-surface p-4 shadow-jp-md"
            role="dialog"
            aria-modal="true"
            aria-label="Customer dashboard navigation"
          >
            <div className="mb-4 flex items-center justify-between">
              <p className="text-jp-sm font-semibold text-jp-text">Dashboard</p>
              <IconButton label="Close dashboard menu" onClick={closeDrawer}>
                <span aria-hidden="true">×</span>
              </IconButton>
            </div>
            {nav}
          </aside>
        </div>
      ) : null}

      <div className="mx-auto flex max-w-jp-container gap-jp-lg px-jp-xl py-jp-lg">
        <aside className="hidden w-jp-sidebar shrink-0 lg:block" aria-label="Customer sidebar">
          <div className="sticky top-[calc(var(--jp-nav-height)+1rem)] space-y-6 rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-sm">
            <div>
              <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Account</p>
              <p className="mt-1 text-jp-sm text-jp-text">
                {session.status === "authenticated" ? session.user.displayName : "Customer"}
              </p>
            </div>
            {nav}
            <div className="border-t border-jp-border pt-4 text-jp-sm">
              <Link href="/" className="text-jp-brand focus-visible:shadow-jp-focus">
                Return to site
              </Link>
              <Link href="/laravel/logout" className="mt-2 block text-jp-muted focus-visible:shadow-jp-focus">
                Sign out
              </Link>
            </div>
          </div>
        </aside>

        <section className="min-w-0 flex-1" aria-labelledby="customer-page-title">
          <div className="mb-jp-lg hidden lg:block">
            <h1 id="customer-page-title" className="font-display text-jp-h2 font-bold text-jp-text">
              {title}
            </h1>
          </div>
          {children}
        </section>
      </div>
    </div>
  );
}

export function CustomerDashboardErrorState({
  message,
  onRetry,
}: {
  message: string;
  onRetry?: () => void;
}) {
  return (
    <ErrorState
      message={message}
      onRetry={onRetry}
      testId="customer-dashboard-error"
      className="text-left"
    />
  );
}

export function CustomerEmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action?: React.ReactNode;
}) {
  return <EmptyState title={title} description={description} action={action} testId="customer-empty-state" />;
}

export function StatusBadge({ status }: { status: { label: string; code: string } }) {
  return (
    <UiStatusBadge data-testid={`status-${status.code}`}>
      {status.label}
    </UiStatusBadge>
  );
}
