"use client";

import type { ReactNode } from "react";
import { cn } from "@/lib/cn";
import type { TripType } from "../types";
import { useSearchTabKeyboard } from "../hooks/use-search-tabs";

type SearchTabsProps = {
  mode: TripType;
  onModeChange: (mode: TripType) => void;
  compact?: boolean;
  /** Optional trailing control (e.g. Travelers) aligned top-right. */
  end?: ReactNode;
};

function OneWayIcon({ active }: { active: boolean }) {
  return (
    <svg
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn(
        "h-5 w-5 shrink-0 transition-transform duration-150 ease-out motion-reduce:transition-none",
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
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn(
        "h-5 w-5 shrink-0 transition-transform duration-150 ease-out motion-reduce:transition-none",
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
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn("h-5 w-5 shrink-0", active && "motion-safe:opacity-100")}
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

/**
 * Trip tabs hang from the search-card ceiling (flush top, rounded bottom).
 * Narrow mobile: icon-only. sm+: full labels. No full-width header divider.
 */
export function SearchTabs({ mode, onModeChange, compact = false, end }: SearchTabsProps) {
  const { modes, modeLabels, handleKeyDown } = useSearchTabKeyboard(mode, onModeChange);

  return (
    <div
      className={cn(
        "flex flex-wrap items-start justify-between gap-2",
        compact ? "-mx-jp-md px-jp-md sm:-mx-jp-lg sm:px-jp-lg" : "-mx-jp-lg px-jp-lg sm:-mx-jp-xl sm:px-jp-xl",
      )}
      data-testid="search-card-header"
    >
      <div
        role="tablist"
        aria-label="Flight trip type"
        data-testid="search-trip-tabs"
        className="flex min-w-0 flex-1 flex-nowrap items-start gap-1 overflow-visible"
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
              aria-label={modeLabels[tabMode]}
              title={modeLabels[tabMode]}
              aria-selected={selected}
              tabIndex={selected ? 0 : -1}
              data-testid={`search-trip-tab-${tabMode}`}
              onClick={() => onModeChange(tabMode)}
              onKeyDown={(event) => handleKeyDown(event, tabMode)}
              className={cn(
                // Hanging tab: flush to card ceiling, rounded bottom only.
                "inline-flex shrink-0 items-center justify-center gap-1.5 border font-semibold transition-colors duration-ui",
                "rounded-t-none rounded-b-jp-md border-t-0",
                compact ? "min-h-11 min-w-11 px-2.5 py-2 text-jp-xs sm:px-3" : "min-h-11 px-3 py-2 text-jp-sm",
                "focus-visible:outline-none focus-visible:shadow-jp-focus",
                selected
                  ? "border-jp-border bg-jp-primary-soft text-jp-primary shadow-jp-sm"
                  : "border-transparent bg-jp-surface-muted/60 text-jp-muted hover:bg-jp-surface-muted hover:text-jp-text",
              )}
            >
              <Icon active={selected} />
              <span className="hidden sm:inline">{modeLabels[tabMode]}</span>
            </button>
          );
        })}
      </div>
      {end ? (
        <div className="mt-1.5 shrink-0 self-start sm:mt-1" data-testid="search-header-travelers">
          {end}
        </div>
      ) : null}
    </div>
  );
}
