"use client";

import { cn } from "@/lib/cn";
import { SORT_CONTROLS, type UiSortKey } from "../utils/sorting";

type ResultsSortTabsProps = {
  value: UiSortKey;
  onChange: (value: UiSortKey) => void;
  className?: string;
};

export function ResultsSortTabs({ value, onChange, className }: ResultsSortTabsProps) {
  return (
    <div
      className={cn(
        "flex flex-wrap gap-1 rounded-jp-card border border-jp-border bg-jp-surface p-1",
        className,
      )}
      role="tablist"
      aria-label="Sort results"
      data-testid="results-sort-tabs"
    >
      {SORT_CONTROLS.map((option) => {
        const selected = value === option.key;
        return (
          <button
            key={option.key}
            type="button"
            role="tab"
            aria-selected={selected}
            className={cn(
              "rounded-jp-md px-3 py-1.5 text-jp-xs font-medium transition-colors motion-reduce:transition-none sm:text-jp-sm",
              selected
                ? "bg-jp-primary text-white"
                : "text-jp-muted hover:bg-jp-surface-muted hover:text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus",
            )}
            onClick={() => onChange(option.key)}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}
