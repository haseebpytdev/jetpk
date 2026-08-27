"use client";

import { GroupTicketingForm } from "@/features/search/components/GroupTicketingForm";
import type { GroupSearchFacetsLoadState, GroupSearchFacetOption } from "../types";
import { GroupCategoryCards } from "./GroupCategoryCards";

export type SharedGroupSearchValues = {
  airline: string;
  sector: string;
  category: string;
  travelDate: string;
};

type SharedGroupSearchProps = {
  values: SharedGroupSearchValues;
  facetsState: GroupSearchFacetsLoadState;
  airlines: GroupSearchFacetOption[];
  sectors: GroupSearchFacetOption[];
  categories: GroupSearchFacetOption[];
  dateBounds?: { minimum?: string; maximum?: string } | null;
  facetsError?: string | null;
  onRetryFacets?: () => void;
  onChange: (next: SharedGroupSearchValues) => void;
  onSubmit: () => void;
  onClear: () => void;
  errors: string[];
  disabled?: boolean;
  className?: string;
};

/**
 * Shared Groups search surface for homepage Groups tab and /groups/search.
 */
export function SharedGroupSearch({
  values,
  facetsState,
  airlines,
  sectors,
  categories,
  dateBounds,
  facetsError,
  onRetryFacets,
  onChange,
  onSubmit,
  onClear,
  errors,
  disabled = false,
  className,
}: SharedGroupSearchProps) {
  return (
    <div className={className} data-testid="shared-group-search">
      <GroupTicketingForm
        airline={values.airline}
        sector={values.sector}
        category={values.category}
        travelDate={values.travelDate}
        facetsState={facetsState}
        airlines={airlines}
        sectors={sectors}
        categories={categories}
        dateBounds={dateBounds}
        facetsError={facetsError}
        onRetryFacets={onRetryFacets}
        onAirlineChange={(airline) => onChange({ ...values, airline })}
        onSectorChange={(sector) => onChange({ ...values, sector })}
        onCategoryChange={(category) => onChange({ ...values, category })}
        onTravelDateChange={(travelDate) => onChange({ ...values, travelDate })}
        onSubmit={onSubmit}
        onClear={onClear}
        errors={errors}
        disabled={disabled}
        showInlineCategory={false}
      />
      <GroupCategoryCards
        categories={categories}
        selected={values.category === "all" ? "" : values.category}
        disabled={disabled || facetsState !== "loaded"}
        onSelect={(category) => {
          onChange({ ...values, category: category === "" ? "all" : category });
        }}
      />
    </div>
  );
}
