"use client";

import { cn } from "@/lib/cn";
import type { SearchMode } from "../types";
import { useSearchTabKeyboard } from "../hooks/use-search-tabs";

type SearchTabsProps = {
  mode: SearchMode;
  onModeChange: (mode: SearchMode) => void;
  compact?: boolean;
};

export function SearchTabs({ mode, onModeChange, compact = false }: SearchTabsProps) {
  const { modes, modeLabels, handleKeyDown } = useSearchTabKeyboard(mode, onModeChange);

  return (
    <div
      role="tablist"
      aria-label="Flight search type"
      className="flex gap-1 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
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
              "shrink-0 rounded-jp-pill font-semibold transition-colors duration-ui",
              compact ? "px-3 py-1.5 text-jp-xs" : "px-4 py-2 text-jp-sm",
              "focus-visible:outline-none focus-visible:shadow-jp-focus",
              selected
                ? "bg-jp-primary text-white shadow-jp-sm"
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
