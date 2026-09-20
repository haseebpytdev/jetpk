"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useId } from "react";
import type { GroupSearchFacetsLoadState, GroupSearchFacetOption } from "@/features/group-ticketing/types";
import { DateField } from "./DateField";
import { todayIsoDate } from "../utils/dates";

type GroupTicketingFormProps = {
  airline: string;
  sector: string;
  travelDate: string;
  facetsState: GroupSearchFacetsLoadState;
  airlines: GroupSearchFacetOption[];
  sectors: GroupSearchFacetOption[];
  dateBounds?: { minimum?: string; maximum?: string } | null;
  facetsError?: string | null;
  onRetryFacets?: () => void;
  onAirlineChange: (value: string) => void;
  onSectorChange: (value: string) => void;
  onTravelDateChange: (value: string) => void;
  onSubmit: () => void;
  errors: string[];
  disabled?: boolean;
};

/**
 * Primary Group search box: Airline | Sector | Travel Date + Search.
 * Category is a results filter via cards/URL — never a form control here.
 */
export function GroupTicketingForm({
  airline,
  sector,
  travelDate,
  facetsState,
  airlines,
  sectors,
  dateBounds,
  facetsError,
  onRetryFacets,
  onAirlineChange,
  onSectorChange,
  onTravelDateChange,
  onSubmit,
  errors,
  disabled = false,
}: GroupTicketingFormProps) {
  const id = useId();
  const facetsLoading = facetsState === "loading";
  const facetsEmpty = facetsState === "empty";
  const facetsFailed = facetsState === "error";
  const facetsReady = facetsState === "loaded" && sectors.length > 0;
  const minDate = dateBounds?.minimum ?? todayIsoDate();
  const maxDate = dateBounds?.maximum;
  const submitDisabled = disabled || facetsLoading || facetsEmpty || facetsFailed || !facetsReady;

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
      className="space-y-4"
      aria-label="Group ticketing search"
      data-testid="group-search-form"
      data-group-search-fields="3"
    >
      <div className="grid gap-3 md:grid-cols-3">
        <div>
          <label htmlFor={`${id}-airline`} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
            Airline
          </label>
          <select
            id={`${id}-airline`}
            value={airline}
            disabled={!facetsReady || disabled}
            onChange={(event) => onAirlineChange(event.target.value)}
            aria-busy={facetsLoading}
            className="w-full min-h-jp-tap rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2.5 text-jp-sm focus-visible:outline-none focus-visible:shadow-jp-focus disabled:cursor-not-allowed disabled:bg-jp-surface-muted disabled:text-jp-muted"
            data-testid="group-airline-select"
          >
            <option value="">{facetsLoading ? "Loading airlines…" : "All Airlines"}</option>
            {airlines.map((item) => (
              <option key={item.value} value={item.value}>
                {item.label}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label htmlFor={`${id}-sector`} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
            Sector
          </label>
          <select
            id={`${id}-sector`}
            value={sector}
            disabled={!facetsReady || disabled}
            onChange={(event) => onSectorChange(event.target.value)}
            aria-busy={facetsLoading}
            aria-invalid={facetsFailed || facetsEmpty}
            aria-describedby={facetsFailed ? `${id}-sector-error` : facetsEmpty ? `${id}-sector-empty` : undefined}
            className="w-full min-h-jp-tap rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2.5 text-jp-sm focus-visible:outline-none focus-visible:shadow-jp-focus disabled:cursor-not-allowed disabled:bg-jp-surface-muted disabled:text-jp-muted"
            data-testid="group-sector-select"
          >
            <option value="">{facetsLoading ? "Loading sectors…" : "Select sector"}</option>
            {sectors.map((item) => (
              <option key={item.value} value={item.value}>
                {item.label}
              </option>
            ))}
          </select>
          {facetsLoading ? (
            <p className="mt-1 text-jp-xs text-jp-muted" role="status" aria-live="polite">
              Loading group inventory…
            </p>
          ) : null}
          {facetsEmpty ? (
            <p id={`${id}-sector-empty`} className="mt-1 text-jp-sm text-jp-muted" role="status">
              No group sectors are currently available. Please check back later.
            </p>
          ) : null}
          {facetsFailed ? (
            <div id={`${id}-sector-error`} className="mt-2 space-y-2" role="alert">
              <p className="text-jp-sm text-red-800">{facetsError ?? "We could not load group search options."}</p>
              {onRetryFacets ? (
                <button
                  type="button"
                  onClick={onRetryFacets}
                  className="rounded-jp-md border border-jp-border px-3 py-1.5 text-jp-sm font-semibold focus-visible:outline-none focus-visible:shadow-jp-focus"
                >
                  Retry loading options
                </button>
              ) : null}
            </div>
          ) : null}
        </div>

        <DateField
          id={`${id}-date`}
          label="Travel date"
          value={travelDate}
          onChange={onTravelDateChange}
          min={minDate}
          max={maxDate}
          disabled={!facetsReady || disabled}
        />
      </div>

      {errors.length > 0 ? (
        <div role="status" aria-live="polite" className="rounded-jp-md border border-red-200 bg-red-50 px-3 py-2 text-jp-sm text-red-800">
          <ul className="list-disc pl-4">
            {errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        </div>
      ) : null}

      <div className="flex justify-end" data-testid="group-search-submit-row">
        <PrimaryButton type="submit" className="w-full sm:w-auto" disabled={submitDisabled}>
          {disabled ? "Searching…" : "Search Group Fares"}
        </PrimaryButton>
      </div>
    </form>
  );
}
