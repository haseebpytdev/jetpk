"use client";

import { cn } from "@/lib/cn";
import { useCallback } from "react";
import type { ProductTab } from "../types";

type SearchServiceSwitcherProps = {
  service: ProductTab;
  onServiceChange: (service: ProductTab) => void;
  className?: string;
};

const SERVICES: ProductTab[] = ["flights", "group"];

const SERVICE_ARIA_LABELS: Record<ProductTab, string> = {
  flights: "Flights",
  group: "Group Ticketing",
};

const SERVICE_VISIBLE_LABELS: Record<ProductTab, string> = {
  flights: "Flights",
  group: "Groups",
};

function FlightsIcon({ active }: { active: boolean }) {
  return (
    <svg
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn(
        "h-5 w-5 shrink-0 transition-transform duration-150 ease-out motion-reduce:transition-none",
        active && "motion-safe:-translate-y-px motion-safe:rotate-[-6deg]",
      )}
    >
      <path d="M2.5 12.5 21 4l-3.5 16-4.5-4.5L8.5 20l-1-4.5L2.5 12.5Z" />
      <path d="M21 4 10.5 14.5" />
    </svg>
  );
}

function GroupTicketingIcon({ active }: { active: boolean }) {
  return (
    <svg
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn(
        "h-5 w-5 shrink-0 transition-transform duration-150 ease-out motion-reduce:transition-none",
        active && "motion-safe:scale-105",
      )}
    >
      <circle cx="9" cy="7" r="2.5" />
      <circle cx="16" cy="8" r="2" />
      <path d="M3.5 19c.5-2.6 2.6-4.3 5.5-4.3s5 1.7 5.5 4.3" />
      <path d="M14 14.5c1.5-.35 3.1.25 4.1 2" />
      <path d="M15.2 16.2h5a.9.9 0 0 1 .9.9v1.6a.9.9 0 0 1-.9.9h-5a.9.9 0 0 1-.9-.9v-1.6a.9.9 0 0 1 .9-.9Z" />
    </svg>
  );
}

/**
 * Three responsive modes:
 * - small mobile: full search-card width horizontal labeled switch
 * - tablet/intermediate: compact centered horizontal labeled switch (not card-wide)
 * - large desktop: compact vertical external icon rail (~48–64px)
 */
export function SearchServiceSwitcher({
  service,
  onServiceChange,
  className,
}: SearchServiceSwitcherProps) {
  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLButtonElement>, current: ProductTab) => {
      const index = SERVICES.indexOf(current);
      if (index === -1) return;

      let nextIndex = index;
      if (event.key === "ArrowRight" || event.key === "ArrowDown") {
        nextIndex = (index + 1) % SERVICES.length;
      }
      if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
        nextIndex = (index - 1 + SERVICES.length) % SERVICES.length;
      }
      if (event.key === "Home") nextIndex = 0;
      if (event.key === "End") nextIndex = SERVICES.length - 1;

      if (nextIndex !== index) {
        event.preventDefault();
        const next = SERVICES[nextIndex]!;
        onServiceChange(next);
        requestAnimationFrame(() => document.getElementById(`search-service-${next}`)?.focus());
      }
    },
    [onServiceChange],
  );

  return (
    <div
      role="tablist"
      aria-label="Search service"
      data-testid="homepage-service-switcher"
      className={cn(
        "flex shrink-0 flex-row gap-1 rounded-jp-md border border-jp-border bg-jp-surface p-1 shadow-jp-sm",
        "w-full md:w-auto md:min-w-[16.5rem] md:justify-center",
        "lg:w-14 lg:min-w-0 lg:flex-col lg:items-center lg:justify-start lg:gap-1 lg:p-1.5",
        className,
      )}
    >
      {SERVICES.map((tab) => {
        const selected = service === tab;
        return (
          <button
            key={tab}
            id={`search-service-${tab}`}
            type="button"
            role="tab"
            aria-label={SERVICE_ARIA_LABELS[tab]}
            title={SERVICE_ARIA_LABELS[tab]}
            aria-selected={selected}
            tabIndex={selected ? 0 : -1}
            data-testid={`search-service-${tab}`}
            onClick={() => onServiceChange(tab)}
            onKeyDown={(event) => handleKeyDown(event, tab)}
            className={cn(
              "inline-flex h-11 min-h-[2.75rem] flex-1 items-center justify-center gap-2 rounded-jp-md px-3 transition-colors duration-ui",
              "lg:h-11 lg:w-11 lg:flex-none lg:px-0",
              "focus-visible:outline-none focus-visible:shadow-jp-focus",
              selected
                ? "bg-jp-primary text-white shadow-jp-sm"
                : "bg-transparent text-jp-muted hover:bg-jp-surface-muted hover:text-jp-text",
            )}
          >
            {tab === "flights" ? <FlightsIcon active={selected} /> : <GroupTicketingIcon active={selected} />}
            <span className="text-jp-sm font-semibold lg:hidden">{SERVICE_VISIBLE_LABELS[tab]}</span>
          </button>
        );
      })}
    </div>
  );
}
