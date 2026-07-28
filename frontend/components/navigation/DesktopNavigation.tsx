"use client";

import { Badge } from "@/components/ui/Badge";
import { Dropdown, DropdownLinkItem } from "@/components/ui/Dropdown";
import { primaryNavigation } from "@/lib/navigation";
import { cn } from "@/lib/cn";
import type { NavItem } from "@/types/navigation";

type DesktopNavigationProps = {
  className?: string;
};

export function DesktopNavigation({ className }: DesktopNavigationProps) {
  return (
    <nav aria-label="Primary" className={cn("hidden items-center gap-1 lg:flex", className)}>
      {primaryNavigation.map((item) => (
        <DesktopNavItem key={item.label} item={item} />
      ))}
    </nav>
  );
}

function DesktopNavItem({ item }: { item: NavItem }) {
  if (item.type === "link") {
    return (
      <a
        href={item.href}
        className="inline-flex items-center gap-2 rounded-jp-md px-3 py-2 text-jp-sm font-medium text-jp-text transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
      >
        <span>{item.label}</span>
        {item.badge ? <Badge variant="new">{item.badge}</Badge> : null}
      </a>
    );
  }

  return (
    <Dropdown
      align="start"
      panelClassName="min-w-[15rem]"
      trigger={({ id, expanded, onToggle, onKeyDown }) => (
        <button
          type="button"
          aria-haspopup="menu"
          aria-expanded={expanded}
          aria-controls={id}
          onClick={onToggle}
          onKeyDown={onKeyDown}
          className="inline-flex items-center gap-1.5 rounded-jp-md px-3 py-2 text-jp-sm font-medium text-jp-text transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          <span>{item.label}</span>
          <ChevronDownIcon className={cn("h-4 w-4 transition-transform", expanded && "rotate-180")} />
        </button>
      )}
    >
      {item.items.map((link) => (
        <DropdownLinkItem key={link.href} href={link.href} description={link.description}>
          {link.label}
        </DropdownLinkItem>
      ))}
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
