"use client";

import { useCallback } from "react";
import type { SearchMode } from "../types";

const SEARCH_MODES: SearchMode[] = ["one_way", "return", "multi_city", "group"];

export const MODE_LABELS: Record<SearchMode, string> = {
  one_way: "One Way",
  return: "Return",
  multi_city: "Multi-City",
  group: "Group Ticketing",
};

export function useSearchTabKeyboard(
  mode: SearchMode,
  onModeChange: (mode: SearchMode) => void,
) {
  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLButtonElement>, current: SearchMode) => {
      const index = SEARCH_MODES.indexOf(current);
      if (index === -1) return;

      let nextIndex = index;
      if (event.key === "ArrowRight") nextIndex = (index + 1) % SEARCH_MODES.length;
      if (event.key === "ArrowLeft") nextIndex = (index - 1 + SEARCH_MODES.length) % SEARCH_MODES.length;
      if (event.key === "Home") nextIndex = 0;
      if (event.key === "End") nextIndex = SEARCH_MODES.length - 1;

      if (nextIndex !== index) {
        event.preventDefault();
        const nextMode = SEARCH_MODES[nextIndex];
        onModeChange(nextMode);
        const tabId = `search-tab-${nextMode}`;
        requestAnimationFrame(() => document.getElementById(tabId)?.focus());
      }
    },
    [onModeChange],
  );

  return { modes: SEARCH_MODES, modeLabels: MODE_LABELS, handleKeyDown };
}
