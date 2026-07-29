"use client";

import { useEffect, useRef } from "react";
import type { ActiveResultsFilters, ResultsFilterMeta } from "../types";
import { ResultsFilterPanel } from "./ResultsFilterPanel";

type MobileFilterDrawerProps = {
  open: boolean;
  onClose: () => void;
  facets: ResultsFilterMeta | undefined;
  filters: ActiveResultsFilters;
  onChange: (filters: ActiveResultsFilters) => void;
  onClearAll: () => void;
  triggerRef: React.RefObject<HTMLButtonElement | null>;
};

export function MobileFilterDrawer({
  open,
  onClose,
  facets,
  filters,
  onChange,
  onClearAll,
  triggerRef,
}: MobileFilterDrawerProps) {
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    closeRef.current?.focus();
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        onClose();
        triggerRef.current?.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [onClose, open, triggerRef]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 lg:hidden" data-testid="mobile-filter-drawer">
      <button type="button" className="absolute inset-0 bg-black/40" aria-label="Close filters" onClick={onClose} />
      <div className="absolute bottom-0 left-0 right-0 max-h-[85vh] overflow-y-auto rounded-t-jp-lg bg-jp-page p-4 shadow-jp-card">
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-base font-semibold text-jp-text">Filters</h2>
          <button
            ref={closeRef}
            type="button"
            className="rounded-jp-md px-3 py-1 text-sm font-medium text-jp-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            onClick={() => {
              onClose();
              triggerRef.current?.focus();
            }}
          >
            Done
          </button>
        </div>
        <ResultsFilterPanel facets={facets} filters={filters} onChange={onChange} onClearAll={onClearAll} />
      </div>
    </div>
  );
}
