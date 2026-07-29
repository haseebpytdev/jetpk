"use client";

import { useRef } from "react";
import { countActiveFilters } from "../utils/filters";
import type { ActiveResultsFilters } from "../types";
import { SortControl } from "./SortControl";
import type { UiSortKey } from "../utils/sorting";

type ResultsToolbarProps = {
  sort: UiSortKey;
  onSortChange: (sort: UiSortKey) => void;
  filters: ActiveResultsFilters;
  onOpenFilters: () => void;
  filterButtonRef?: React.RefObject<HTMLButtonElement | null>;
  total: number;
};

export function ResultsToolbar({ sort, onSortChange, filters, onOpenFilters, filterButtonRef, total }: ResultsToolbarProps) {
  const internalRef = useRef<HTMLButtonElement>(null);
  const buttonRef = filterButtonRef ?? internalRef;
  const activeFilters = countActiveFilters(filters);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 rounded-jp-card border border-jp-border bg-jp-surface px-3 py-2 sm:px-4">
      <p className="text-sm text-jp-text-muted">{total} result{total === 1 ? "" : "s"}</p>
      <div className="flex items-center gap-2">
        <button
          ref={buttonRef}
          type="button"
          className="rounded-jp-md border border-jp-border px-3 py-1.5 text-sm font-medium text-jp-text hover:bg-jp-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary lg:hidden"
          onClick={onOpenFilters}
          data-testid="open-mobile-filters"
        >
          Filters{activeFilters > 0 ? ` (${activeFilters})` : ""}
        </button>
        <SortControl value={sort} onChange={onSortChange} />
      </div>
    </div>
  );
}

export { ResultsToolbar as ResultsToolbarWithRef };
