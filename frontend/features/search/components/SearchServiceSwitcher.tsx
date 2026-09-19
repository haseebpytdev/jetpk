"use client";

import { cn } from "@/lib/cn";
import { useCallback } from "react";
import type { ProductTab } from "../types";

type SearchServiceSwitcherProps = {
  service: ProductTab;
  onServiceChange: (service: ProductTab) => void;
  /** Vertical rail on lg+; horizontal strip below. */
  orientation?: "auto" | "vertical" | "horizontal";
  className?: string;
};

const SERVICES: ProductTab[] = ["flights", "group"];

const SERVICE_LABELS: Record<ProductTab, string> = {
  flights: "Flights",
  group: "Group Ticketing",
};

function FlightsIcon({ active }: { active: boolean }) {
  return (
    <svg
      width="22"
      height="22"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn(
        "transition-transform duration-200 ease-out motion-reduce:transition-none",
        active && "motion-safe:-translate-y-0.5 motion-safe:rotate-[-8deg]",
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
      width="22"
      height="22"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn(
        "transition-transform duration-200 ease-out motion-reduce:transition-none",
        active && "motion-safe:scale-105",
      )}
    >
      <circle cx="9" cy="7" r="2.75" />
      <circle cx="16.5" cy="8" r="2.25" />
      <path d="M3.5 19c.5-2.8 2.7-4.6 5.5-4.6s5 1.8 5.5 4.6" />
      <path d="M14 14.4c1.6-.4 3.3.2 4.4 2.1" />
      <path d="M15.5 16.5h5.2a1 1 0 0 1 1 1v1.8a1 1 0 0 1-1 1h-5.2a1 1 0 0 1-1-1v-1.8a1 1 0 0 1 1-1Z" />
      <path d="M15.5 18.4h5.2" strokeDasharray="1.5 1.5" />
    </svg>
  );
}

export function SearchServiceSwitcher({
  service,
  onServiceChange,
  orientation = "auto",
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

  const isVertical =
    orientation === "vertical" || orientation === "auto";

  return (
    <div
      role="tablist"
      aria-label="Search service"
      data-testid="homepage-service-switcher"
      aria-orientation={orientation === "horizontal" ? "horizontal" : undefined}
      className={cn(
        "shrink-0",
        orientation === "auto" &&
          "flex w-full flex-row gap-2 lg:w-auto lg:flex-col lg:gap-2",
        orientation === "vertical" && "flex flex-col gap-2",
        orientation === "horizontal" && "flex w-full flex-row gap-2",
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
            aria-selected={selected}
            tabIndex={selected ? 0 : -1}
            data-testid={`search-service-${tab}`}
            onClick={() => onServiceChange(tab)}
            onKeyDown={(event) => handleKeyDown(event, tab)}
            className={cn(
              "inline-flex min-w-0 flex-1 items-center gap-2 rounded-jp-md border px-3 py-2.5 text-left transition-colors duration-ui",
              "focus-visible:outline-none focus-visible:shadow-jp-focus",
              isVertical && "lg:flex-none lg:w-[7.25rem] lg:flex-col lg:items-center lg:justify-center lg:gap-1.5 lg:px-2 lg:py-3 lg:text-center",
              selected
                ? "border-jp-primary bg-jp-primary text-white shadow-jp-sm"
                : "border-jp-border/80 bg-jp-surface/90 text-jp-muted hover:border-jp-primary/40 hover:bg-jp-surface hover:text-jp-text",
            )}
          >
            {tab === "flights" ? <FlightsIcon active={selected} /> : <GroupTicketingIcon active={selected} />}
            <span className={cn("font-semibold leading-tight", "text-jp-xs lg:text-[0.7rem]")}>
              {SERVICE_LABELS[tab]}
            </span>
          </button>
        );
      })}
    </div>
  );
}
