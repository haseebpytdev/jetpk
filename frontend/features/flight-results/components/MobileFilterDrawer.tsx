"use client";

import { Drawer } from "@/components/ui/Drawer";
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
}: MobileFilterDrawerProps) {
  return (
    <Drawer open={open} onClose={onClose} title="Filters" className="lg:hidden max-w-full">
      <div data-testid="mobile-filter-drawer">
        <ResultsFilterPanel facets={facets} filters={filters} onChange={onChange} onClearAll={onClearAll} />
      </div>
    </Drawer>
  );
}
