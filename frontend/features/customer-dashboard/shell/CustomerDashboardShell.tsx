"use client";

import { usePathname } from "next/navigation";
import { useState } from "react";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorState } from "@/components/ui/ErrorState";
import { StatusBadge as UiStatusBadge } from "@/components/ui/StatusBadge";
import {
  PortalContent,
  PortalMobileDrawer,
  PortalPageHeader,
  PortalShell,
  PortalSidebar,
  PortalSidebarFooter,
  PortalTopbar,
  buildPortalNav,
  type PortalNavItem,
} from "@/features/portal";
import type { PublicSession } from "@/types/session";

const NAV_ITEMS: PortalNavItem[] = [
  { href: "/customer/dashboard", label: "Overview", code: "overview" },
  { href: "/customer/bookings", label: "My Bookings", code: "bookings" },
  { href: "/customer/payments", label: "Payments", code: "payments" },
  { href: "/customer/invoices", label: "Invoices", code: "invoices" },
  { href: "/customer/travelers", label: "Saved travelers", code: "travelers" },
  { href: "/customer/profile", label: "Profile", code: "profile" },
  { href: "/customer/security", label: "Security", code: "security" },
  { href: "/customer/support", label: "Support", code: "support" },
  { href: "/customer/notifications", label: "Notifications", code: "notifications" },
];

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

  const navItems = NAV_ITEMS.map((item) =>
    item.code === "notifications" ? { ...item, badge: unreadNotifications } : item,
  );

  const nav = buildPortalNav(navItems, pathname, closeDrawer, "Customer dashboard");
  const identityValue = session.status === "authenticated" ? session.user.displayName : "Customer";

  return (
    <PortalShell
      testId="customer-dashboard-shell"
      topbar={<PortalTopbar title={title} drawerOpen={drawerOpen} onToggleDrawer={() => setDrawerOpen((open) => !open)} />}
      drawer={
        <PortalMobileDrawer open={drawerOpen} onClose={closeDrawer} title="Dashboard" nav={nav} footer={<PortalSidebarFooter />} />
      }
      sidebar={
        <PortalSidebar identityLabel="Account" identityValue={identityValue} nav={nav} footer={<PortalSidebarFooter />} />
      }
      content={
        <PortalContent titleId="customer-page-title">
          <PortalPageHeader title={title} id="customer-page-title" />
          {children}
        </PortalContent>
      }
    />
  );
}

export function CustomerDashboardErrorState({
  message,
  onRetry,
}: {
  message: string;
  onRetry?: () => void;
}) {
  return <ErrorState message={message} onRetry={onRetry} testId="customer-dashboard-error" className="text-left" />;
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
