"use client";

import type { ReactNode } from "react";
import { cn } from "@/lib/cn";
import type { TripType } from "../types";
import { useSearchTabKeyboard } from "../hooks/use-search-tabs";

type SearchTabsProps = {
  mode: TripType;
  onModeChange: (mode: TripType) => void;
  compact?: boolean;
  /** Optional trailing control (e.g. Travelers & Cabin) aligned top-right. */
  end?: ReactNode;
};

function OneWayIcon({ active }: { active: boolean }) {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn(
        "shrink-0 transition-transform duration-150 ease-out motion-reduce:transition-none",
        active && "motion-safe:translate-x-0.5",
      )}
    >
      <path d="M4 12h14" />
      <path d="M14 6l6 6-6 6" />
    </svg>
  );
}

function ReturnIcon({ active }: { active: boolean }) {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn(
        "shrink-0 transition-transform duration-150 ease-out motion-reduce:transition-none",
        active && "motion-safe:scale-110",
      )}
    >
      <path d="M7 7h10l-2-2" />
      <path d="M17 17H7l2 2" />
      <path d="M7 7v4a4 4 0 0 0 4 4h6" />
      <path d="M17 17v-4a4 4 0 0 0-4-4H7" />
    </svg>
  );
}

function MultiCityIcon({ active }: { active: boolean }) {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn("shrink-0", active && "motion-safe:opacity-100")}
    >
      <circle cx="5" cy="12" r="2.25" fill="currentColor" stroke="none" />
      <circle cx="12" cy="7" r="2.25" fill="currentColor" stroke="none" />
      <circle cx="19" cy="12" r="2.25" fill="currentColor" stroke="none" />
      <path d="M7 11.2 10.2 8.4M13.8 8.4 17 11.2" />
    </svg>
  );
}

const TRIP_ICONS: Record<TripType, (props: { active: boolean }) => ReactNode> = {
  one_way: OneWayIcon,
  return: ReturnIcon,
  multi_city: MultiCityIcon,
};

export function SearchTabs({ mode, onModeChange, compact = false, end }: SearchTabsProps) {
  const { modes, modeLabels, handleKeyDown } = useSearchTabKeyboard(mode, onModeChange);

  return (
    <div
      className={cn(
        "flex flex-wrap items-end justify-between gap-2 border-b border-jp-border",
        compact ? "-mx-jp-md px-jp-md sm:-mx-jp-lg sm:px-jp-lg" : "-mx-jp-lg px-jp-lg sm:-mx-jp-xl sm:px-jp-xl",
      )}
      data-testid="search-card-header"
    >
      <div
        role="tablist"
        aria-label="Flight trip type"
        data-testid="search-trip-tabs"
        className="flex min-w-0 flex-1 gap-0.5 overflow-x-auto pb-0 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {modes.map((tabMode) => {
          const selected = mode === tabMode;
          const Icon = TRIP_ICONS[tabMode];
          return (
            <button
              key={tabMode}
              id={`search-tab-${tabMode}`}
              type="button"
              role="tab"
              aria-selected={selected}
              tabIndex={selected ? 0 : -1}
              data-testid={`search-trip-tab-${tabMode}`}
              onClick={() => onModeChange(tabMode)}
              onKeyDown={(event) => handleKeyDown(event, tabMode)}
              className={cn(
                "inline-flex shrink-0 items-center gap-1.5 rounded-t-jp-md border border-b-0 font-semibold transition-colors duration-ui",
                compact ? "px-2.5 py-1.5 text-jp-xs" : "px-3 py-2 text-jp-sm",
                "focus-visible:outline-none focus-visible:shadow-jp-focus",
                selected
                  ? "-mb-px border-jp-border bg-jp-surface text-jp-primary"
                  : "border-transparent bg-transparent text-jp-muted hover:bg-jp-surface-muted hover:text-jp-text",
              )}
            >
              <Icon active={selected} />
              {modeLabels[tabMode]}
            </button>
          );
        })}
      </div>
      {end ? (
        <div className="mb-1.5 shrink-0 self-center" data-testid="search-header-travelers">
          {end}
        </div>
      ) : null}
    </div>
  );
}
