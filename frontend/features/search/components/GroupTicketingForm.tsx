"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useId } from "react";
import type { GroupSearchFacetsLoadState, GroupSearchFacetOption } from "@/features/group-ticketing/types";
import { DateField } from "./DateField";
import { todayIsoDate } from "../utils/dates";

type GroupTicketingFormProps = {
  sector: string;
  category: string;
  travelDate: string;
  facetsState: GroupSearchFacetsLoadState;
  sectors: GroupSearchFacetOption[];
  categories: GroupSearchFacetOption[];
  dateBounds?: { minimum?: string; maximum?: string } | null;
  facetsError?: string | null;
  onRetryFacets?: () => void;
  onSectorChange: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onTravelDateChange: (value: string) => void;
  onSubmit: () => void;
  errors: string[];
  disabled?: boolean;
};

export function GroupTicketingForm({
  sector,
  category,
  travelDate,
  facetsState,
  sectors,
  categories,
  dateBounds,
  facetsError,
  onRetryFacets,
  onSectorChange,
  onCategoryChange,
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
    >
      <p className="text-jp-xs text-jp-muted">
        Passenger counts are collected later during group booking. Search uses sector, travel date, and category only.
      </p>

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

      <div className="grid gap-3 sm:grid-cols-2">
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
            aria-describedby={facetsFailed ? `${id}-sector-error` : facetsEmpty ? `${id}-group-empty` : undefined}
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
            <p className="mt-1 text-jp-xs text-jp-muted" role="status" aria-live="polite">Loading group sectors from live inventory…</p>
          ) : null}
          {facetsFailed ? (
            <div id={`${id}-sector-error`} className="mt-2 space-y-2" role="alert">
              <p className="text-jp-sm text-red-800">{facetsError ?? "We could not load group sectors."}</p>
              {onRetryFacets ? (
                <button
                  type="button"
                  onClick={onRetryFacets}
                  className="rounded-jp-md border border-jp-border px-3 py-1.5 text-jp-sm font-semibold focus-visible:outline-none focus-visible:shadow-jp-focus"
                >
                  Retry loading sectors
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

      <div>
        <span className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Category</span>
        <div
          role="radiogroup"
          aria-label="Group category"
          aria-busy={facetsLoading}
          className="flex flex-wrap gap-2"
          data-testid="group-category-options"
        >
          <label
            className="inline-flex cursor-pointer items-center gap-2 rounded-jp-pill border border-jp-border px-3 py-1.5 text-jp-sm has-[:checked]:border-jp-primary has-[:checked]:bg-jp-primary-soft has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60"
          >
            <input
              type="radio"
              name={`${id}-category`}
              value="all"
              checked={category === "all"}
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

      {errors.length > 0 ? (
        <div role="status" aria-live="polite" className="rounded-jp-md border border-red-200 bg-red-50 px-3 py-2 text-jp-sm text-red-800">
          <ul className="list-disc pl-4">
            {errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        </div>
      ) : null}

      <PrimaryButton type="submit" className="w-full sm:w-auto" disabled={submitDisabled}>
        {disabled ? "Searching…" : "Search Group Fares"}
      </PrimaryButton>
    </form>
  );
}
