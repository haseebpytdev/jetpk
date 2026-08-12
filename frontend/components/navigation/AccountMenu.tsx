"use client";

import { Dropdown } from "@/components/ui/Dropdown";
import { logout } from "@/features/auth/services/auth-service";
import { cn } from "@/lib/cn";
import type { AuthenticatedSession, PublicSession } from "@/types/session";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

type AccountMenuProps = {
  session: PublicSession;
  className?: string;
  compact?: boolean;
};

type AccountMenuLink = {
  href: string;
  label: string;
};

function accountMenuLinks(session: AuthenticatedSession): AccountMenuLink[] {
  if (session.accountType === "customer") {
    return [
      { href: "/customer/dashboard", label: "Overview" },
      { href: "/customer/bookings", label: "My Bookings" },
      { href: "/customer/profile", label: "Profile" },
      { href: "/customer/support", label: "Support" },
    ];
  }

  if (session.accountType === "agent" || session.accountType === "agent_staff") {
    return [
      { href: "/agent/dashboard", label: "Overview" },
      { href: "/agent/bookings", label: "Bookings" },
      { href: "/agent/wallet", label: "Wallet" },
      { href: "/agent/profile", label: "Profile" },
      { href: "/agent/support", label: "Support" },
    ];
  }

  if (session.accountType === "staff" || session.role === "staff" || session.portalType === "staff") {
    return [
      { href: session.dashboardUrl || "/staff/dashboard", label: "Staff dashboard" },
      { href: "/staff/dashboard/bookings", label: "Bookings" },
    ];
  }

  if (
    session.accountType === "platform_admin" ||
    session.accountType === "admin" ||
    session.portalType === "admin" ||
    session.role === "admin"
  ) {
    return [
      { href: session.dashboardUrl || "/admin/dashboard", label: "Admin dashboard" },
      { href: "/admin/dashboard/bookings", label: "Bookings" },
    ];
  }

  return [{ href: session.dashboardUrl || "/", label: "Overview" }];
}

export function AccountMenu({ session, className, compact = false }: AccountMenuProps) {
  const router = useRouter();
  const [loggingOut, setLoggingOut] = useState(false);

  if (session.status === "anonymous") {
    return (
      <Link
        href="/login"
        data-testid={compact ? "account-menu-anonymous-compact" : "account-menu-anonymous-desktop"}
        className={cn(
          compact ? "text-jp-sm font-semibold text-jp-text" : undefined,
          !compact &&
            "inline-flex min-h-jp-button items-center justify-center rounded-jp-button border border-jp-border px-4 text-jp-sm font-semibold text-jp-text transition-colors hover:border-jp-primary hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus",
          className,
        )}
      >
        {compact ? "Account" : "Log in / Sign up"}
      </Link>
    );
  }

  const { user } = session;
  const menuLinks = accountMenuLinks(session);

  async function handleLogout() {
    if (loggingOut) return;
    setLoggingOut(true);
    const result = await logout();
    if (result.ok) {
      window.location.assign(result.redirect);
      return;
    }
    setLoggingOut(false);
    router.refresh();
  }

  return (
    <Dropdown
      className={className}
      align="end"
      portal={compact}
      panelTestId={compact ? "account-menu-panel-compact" : "account-menu-panel-desktop"}
      panelClassName="min-w-[12.5rem]"
      trigger={({ id, expanded, onToggle, onKeyDown, triggerRef }) => (
        <button
          type="button"
          ref={triggerRef}
          data-testid={compact ? "account-menu-trigger-compact" : "account-menu-trigger-desktop"}
          aria-label={`Account menu for ${user.displayName}`}
          aria-haspopup="menu"
          aria-expanded={expanded}
          aria-controls={id}
          onClick={onToggle}
          onKeyDown={onKeyDown}
          className={cn(
            "inline-flex min-h-jp-button items-center gap-2 rounded-jp-pill border border-jp-border bg-jp-surface px-2.5 py-1.5 text-jp-sm font-medium text-jp-text",
            "transition-colors hover:border-jp-primary hover:bg-jp-primary-soft",
            "focus-visible:outline-none focus-visible:shadow-jp-focus",
          )}
        >
          {user.avatarUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={user.avatarUrl}
              alt=""
              className="h-8 w-8 rounded-full object-cover"
            />
          ) : (
            <span
              aria-hidden="true"
              className="flex h-8 w-8 items-center justify-center rounded-full bg-jp-primary text-xs font-bold text-white"
            >
              {user.initials}
            </span>
          )}
          {!compact ? <span className="max-w-[8rem] truncate">{user.displayName}</span> : null}
          <ChevronDownIcon className={cn("h-4 w-4", expanded && "rotate-180")} />
        </button>
      )}
    >
      <div className="border-b border-jp-border px-3 py-2">
        <p className="truncate text-jp-sm font-semibold text-jp-text">{user.displayName}</p>
        <p className="truncate text-jp-xs text-jp-muted">{user.email}</p>
      </div>
      {menuLinks.map((link) => (
        <a
          key={`${link.href}:${link.label}`}
          href={link.href}
          role="menuitem"
          className="block rounded-jp-sm px-3 py-2 text-jp-sm text-jp-text transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          {link.label}
        </a>
      ))}
      <button
        type="button"
        role="menuitem"
        disabled={loggingOut}
        onClick={() => void handleLogout()}
        className="block w-full rounded-jp-sm px-3 py-2 text-left text-jp-sm text-jp-muted transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus disabled:opacity-60"
      >
        {loggingOut ? "Signing out…" : "Log out"}
      </button>
    </Dropdown>
  );
}

function ChevronDownIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path
        d="M5 7.5L10 12.5L15 7.5"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
