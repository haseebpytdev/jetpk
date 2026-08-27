"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { Select } from "@/components/ui/FormControls";
import { useId } from "react";
import type { GroupSearchFacetsLoadState, GroupSearchFacetOption } from "@/features/group-ticketing/types";
import { DateField } from "./DateField";
import { todayIsoDate } from "../utils/dates";
import { cn } from "@/lib/cn";

type GroupTicketingFormProps = {
  airline: string;
  sector: string;
  category: string;
  travelDate: string;
  facetsState: GroupSearchFacetsLoadState;
  airlines: GroupSearchFacetOption[];
  sectors: GroupSearchFacetOption[];
  categories: GroupSearchFacetOption[];
  dateBounds?: { minimum?: string; maximum?: string } | null;
  facetsError?: string | null;
  onRetryFacets?: () => void;
  onAirlineChange: (value: string) => void;
  onSectorChange: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onTravelDateChange: (value: string) => void;
  onSubmit: () => void;
  onClear: () => void;
  errors: string[];
  disabled?: boolean;
  /** Hide inline category radios when category cards own selection. */
  showInlineCategory?: boolean;
};

function FieldIcon({ children }: { children: React.ReactNode }) {
  return (
    <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-jp-muted" aria-hidden="true">
      {children}
    </span>
  );
}

export function GroupTicketingForm({
  airline,
  sector,
  category,
  travelDate,
  facetsState,
  airlines,
  sectors,
  categories,
  dateBounds,
  facetsError,
  onRetryFacets,
  onAirlineChange,
  onSectorChange,
  onCategoryChange,
  onTravelDateChange,
  onSubmit,
  onClear,
  errors,
  disabled = false,
  showInlineCategory = false,
}: GroupTicketingFormProps) {
  const id = useId();
  const facetsLoading = facetsState === "loading";
  const facetsEmpty = facetsState === "empty";
  const facetsFailed = facetsState === "error";
  const facetsReady = facetsState === "loaded";
  const minDate = dateBounds?.minimum ?? todayIsoDate();
  const maxDate = dateBounds?.maximum;
  const submitDisabled = disabled || facetsLoading || facetsEmpty || facetsFailed || !facetsReady;
  const selectClass =
    "min-h-jp-tap py-2.5 pl-9 text-jp-sm font-[Inter,system-ui,sans-serif] disabled:bg-jp-surface-muted disabled:text-jp-muted";

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
      className="space-y-3 font-[Inter,system-ui,sans-serif]"
      aria-label="Groups search"
      data-testid="group-search-form"
    >
      {facetsEmpty ? (
        <div
          id={`${id}-group-empty`}
          className="rounded-jp-md border border-jp-border bg-jp-surface-muted px-4 py-3 text-jp-sm text-jp-text"
          role="status"
          aria-live="polite"
          data-testid="group-empty-state"
        >
          <p>No group fares are currently available. Please check again later or contact JetPakistan Groups.</p>
          {onRetryFacets ? (
            <button
              type="button"
              onClick={onRetryFacets}
              className="mt-2 rounded-jp-md border border-jp-border px-3 py-1.5 text-jp-sm font-semibold focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              Retry
            </button>
          ) : null}
        </div>
      ) : null}

      <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1.1fr)_minmax(0,1fr)_auto_auto]">
        <div>
          <label htmlFor={`${id}-airline`} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
            Airline
          </label>
          <div className="relative">
            <FieldIcon>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d="M2.5 12.5 21 4l-3.5 16-4.5-4.5L8.5 20l-1-4.5L2.5 12.5Z" />
                <path d="M21 4 10.5 14.5" />
              </svg>
            </FieldIcon>
            <Select
              id={`${id}-airline`}
              value={airline}
              disabled={!facetsReady || disabled}
              onChange={(event) => onAirlineChange(event.target.value)}
              aria-busy={facetsLoading}
              className={cn(selectClass)}
              data-testid="group-airline-select"
            >
              <option value="">{facetsLoading ? "Loading airlines…" : "Any airline"}</option>
              {airlines.map((item) => (
                <option key={item.value} value={item.value}>
                  {item.label}
                </option>
              ))}
            </Select>
          </div>
        </div>

        <div>
          <label htmlFor={`${id}-sector`} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
            Sector
          </label>
          <div className="relative">
            <FieldIcon>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
                <path d="M4 12h16M8 8l-4 4 4 4M16 8l4 4-4 4" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            </FieldIcon>
            <Select
              id={`${id}-sector`}
              value={sector}
              disabled={!facetsReady || disabled}
              onChange={(event) => onSectorChange(event.target.value)}
              aria-busy={facetsLoading}
              aria-invalid={facetsFailed || facetsEmpty}
              aria-describedby={facetsFailed ? `${id}-sector-error` : facetsEmpty ? `${id}-group-empty` : undefined}
              className={cn(selectClass)}
              data-testid="group-sector-select"
            >
              <option value="">{facetsLoading ? "Loading sectors…" : "Any sector"}</option>
              {sectors.map((item) => (
                <option key={item.value} value={item.value}>
                  {item.label}
                </option>
              ))}
            </Select>
          </div>
          {facetsLoading ? (
            <p className="mt-1 text-jp-xs text-jp-muted" role="status" aria-live="polite">
              Loading filters from live inventory…
            </p>
          ) : null}
          {facetsFailed ? (
            <div id={`${id}-sector-error`} className="mt-2 space-y-2" role="alert">
              <p className="text-jp-sm text-red-800">{facetsError ?? "We could not load group filters."}</p>
              {onRetryFacets ? (
                <button
                  type="button"
                  onClick={onRetryFacets}
                  className="rounded-jp-md border border-jp-border px-3 py-1.5 text-jp-sm font-semibold focus-visible:outline-none focus-visible:shadow-jp-focus"
                >
                  Retry loading filters
                </button>
              ) : null}
            </div>
          ) : null}
        </div>

        <div className="flex flex-col justify-end">
          <DateField
            id={`${id}-date`}
            label="Travel date"
            value={travelDate}
            onChange={onTravelDateChange}
            min={minDate}
            max={maxDate}
            disabled={!facetsReady || disabled}
            density="compact"
          />
        </div>

        <div className="flex flex-col justify-end">
          <PrimaryButton type="submit" className="w-full min-w-[8.5rem] lg:w-auto" disabled={submitDisabled} data-testid="group-search-submit">
            {disabled ? "Searching…" : "Search Groups"}
          </PrimaryButton>
        </div>

        <div className="flex flex-col justify-end">
          <button
            type="button"
            onClick={onClear}
            disabled={disabled}
            className="inline-flex min-h-jp-tap w-full items-center justify-center rounded-jp-md border border-jp-border bg-jp-surface px-4 py-2.5 text-jp-sm font-semibold text-jp-text hover:bg-jp-surface-muted focus-visible:outline-none focus-visible:shadow-jp-focus disabled:opacity-60 lg:w-auto"
            data-testid="group-search-clear"
          >
            Clear
          </button>
        </div>
      </div>

      {showInlineCategory ? (
        <div>
          <span className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Category</span>
          <div
            role="radiogroup"
            aria-label="Group category"
            aria-busy={facetsLoading}
            className="flex flex-wrap gap-2"
            data-testid="group-category-options"
          >
            <label className="inline-flex cursor-pointer items-center gap-2 rounded-jp-pill border border-jp-border px-3 py-1.5 text-jp-sm has-[:checked]:border-jp-primary has-[:checked]:bg-jp-primary-soft has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
              <input
                type="radio"
                name={`${id}-category`}
                value="all"
                checked={category === "all" || category === ""}
                disabled={!facetsReady || disabled}
                onChange={() => onCategoryChange("all")}
                className="text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
              />
              <span>All</span>
            </label>
            {categories.map((item) => (
              <label
                key={item.value}
                className="inline-flex cursor-pointer items-center gap-2 rounded-jp-pill border border-jp-border px-3 py-1.5 text-jp-sm has-[:checked]:border-jp-primary has-[:checked]:bg-jp-primary-soft has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60"
              >
                <input
                  type="radio"
                  name={`${id}-category`}
                  value={item.value}
                  checked={category === item.value}
                  disabled={!facetsReady || disabled}
                  onChange={() => onCategoryChange(item.value)}
                  className="text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
                />
                <span>{item.label}</span>
              </label>
            ))}
          </div>
        </div>
      ) : null}

      {errors.length > 0 ? (
        <div role="status" aria-live="polite" className="rounded-jp-md border border-red-200 bg-red-50 px-3 py-2 text-jp-sm text-red-800">
          <ul className="list-disc pl-4">
            {errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        </div>
      ) : null}
    </form>
  );
}
