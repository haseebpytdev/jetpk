"use client";

import { Dropdown } from "@/components/ui/Dropdown";
import { cn } from "@/lib/cn";
import type { PublicSession } from "@/types/session";

type AccountMenuProps = {
  session: PublicSession;
  className?: string;
  compact?: boolean;
};

export function AccountMenu({ session, className, compact = false }: AccountMenuProps) {
  if (session.status === "anonymous") {
    return (
      <a
        href="/login"
        className={cn(
          compact ? "text-jp-sm font-semibold text-jp-text" : undefined,
          !compact &&
            "inline-flex min-h-jp-button items-center justify-center rounded-jp-button border border-jp-border px-4 text-jp-sm font-semibold text-jp-text transition-colors hover:border-jp-primary hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus",
          className,
        )}
      >
        {compact ? "Account" : "Log in / Sign up"}
      </a>
    );
  }

  const { user } = session;

  return (
    <Dropdown
      className={className}
      align="end"
      panelClassName="min-w-[12.5rem]"
      trigger={({ id, expanded, onToggle, onKeyDown }) => (
        <button
          type="button"
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
          <span
            aria-hidden="true"
            className="flex h-8 w-8 items-center justify-center rounded-full bg-jp-primary text-xs font-bold text-white"
          >
            {user.initials}
          </span>
          {!compact ? <span className="max-w-[8rem] truncate">{user.displayName}</span> : null}
          <ChevronDownIcon className={cn("h-4 w-4", expanded && "rotate-180")} />
        </button>
      )}
    >
      <div className="border-b border-jp-border px-3 py-2">
        <p className="truncate text-jp-sm font-semibold text-jp-text">{user.displayName}</p>
        <p className="truncate text-jp-xs text-jp-muted">{user.email}</p>
      </div>
      <a
        href="/account"
        role="menuitem"
        className="block rounded-jp-sm px-3 py-2 text-jp-sm text-jp-text transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
      >
        Dashboard
      </a>
      <a
        href="/account/bookings"
        role="menuitem"
        className="block rounded-jp-sm px-3 py-2 text-jp-sm text-jp-text transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
      >
        Bookings
      </a>
      <button
        type="button"
        role="menuitem"
        className="block w-full rounded-jp-sm px-3 py-2 text-left text-jp-sm text-jp-muted transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
      >
        Log out
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
