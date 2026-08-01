"use client";

import { cn } from "@/lib/cn";
import type { SearchMode } from "../types";
import { useSearchTabKeyboard } from "../hooks/use-search-tabs";

type SearchTabsProps = {
  mode: SearchMode;
  onModeChange: (mode: SearchMode) => void;
  compact?: boolean;
  variant?: "default" | "blueprint";
};

export function SearchTabs({ mode, onModeChange, compact = false, variant = "default" }: SearchTabsProps) {
  const { modes, modeLabels, handleKeyDown } = useSearchTabKeyboard(mode, onModeChange);

  return (
    <div
      role="tablist"
      aria-label="Flight search type"
      data-testid={variant === "blueprint" ? "search-tab-row" : undefined}
      className={cn(
        "flex gap-0.5 overflow-x-auto pb-0 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden",
        variant === "blueprint" && "max-h-9 gap-0 overflow-hidden rounded-t-[1.15rem] bg-jp-surface-muted/60 px-1 pt-1",
      )}
    >
      {modes.map((tabMode) => {
        const selected = mode === tabMode;
        return (
          <button
            key={tabMode}
            id={`search-tab-${tabMode}`}
            type="button"
            role="tab"
            aria-selected={selected}
            tabIndex={selected ? 0 : -1}
            onClick={() => onModeChange(tabMode)}
            onKeyDown={(event) => handleKeyDown(event, tabMode)}
            className={cn(
              "shrink-0 font-semibold transition-colors duration-ui",
              variant === "blueprint"
                ? compact
                  ? "rounded-t-lg px-4 py-2 text-jp-xs"
                  : "rounded-t-lg px-5 py-2.5 text-jp-sm"
                : compact
                  ? "rounded-jp-pill px-3 py-1.5 text-jp-xs"
                  : "rounded-jp-pill px-4 py-2 text-jp-sm",
              "focus-visible:outline-none focus-visible:shadow-jp-focus",
              selected
                ? variant === "blueprint"
                  ? "border border-b-0 border-jp-border bg-jp-surface text-jp-primary shadow-[0_-2px_8px_rgba(0,0,0,0.04)]"
                  : "bg-jp-primary text-white shadow-jp-sm"
                : variant === "blueprint"
                  ? "bg-transparent text-jp-muted hover:text-jp-text"
                  : "bg-jp-surface-muted text-jp-muted hover:bg-jp-primary-soft hover:text-jp-text",
            )}
          >
            {modeLabels[tabMode]}
          </button>
        );
      })}
    </div>
  );
}
