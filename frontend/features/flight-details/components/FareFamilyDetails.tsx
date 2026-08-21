"use client";

import { cn } from "@/lib/cn";
import { shouldUseFareCarousel } from "@/lib/fare-selection-authority";
import { useCallback, useRef } from "react";
import type { FareFamilyOption } from "../types";
import { formatWholePkr } from "@/features/flight-results/utils/price";

type FareFamilyDetailsProps = {
  options: FareFamilyOption[];
  selectedKey: string;
  onSelect: (key: string) => void;
  onViewDetails: (key: string) => Promise<void>;
  disabled?: boolean;
};

function benefitRows(option: FareFamilyOption): Array<{ label: string; value: string }> {
  const cabin = option.cabin_baggage ?? option.carry_on_summary ?? null;
  const checked = option.checked_baggage ?? option.check_in_summary ?? null;
  const rows: Array<{ label: string; value: string }> = [];
  if (cabin) rows.push({ label: "Cabin bag", value: cabin });
  if (checked) rows.push({ label: "Checked bag", value: checked });
  if (!cabin && !checked && option.baggage) rows.push({ label: "Baggage", value: option.baggage });
  if (option.refund_rule) rows.push({ label: "Refund", value: option.refund_rule });
  if (option.change_rule) rows.push({ label: "Changes", value: option.change_rule });
  if (option.meal) rows.push({ label: "Meal", value: option.meal });
  if (option.seat_selection) rows.push({ label: "Seat", value: option.seat_selection });
  return rows;
}

export function FareFamilyDetails({ options, selectedKey, onSelect, onViewDetails, disabled }: FareFamilyDetailsProps) {
  const scrollerRef = useRef<HTMLDivElement>(null);

  const scrollBy = useCallback((direction: -1 | 1) => {
    const node = scrollerRef.current;
    if (!node) return;
    const card = node.querySelector<HTMLElement>("[data-fare-family-card]");
    const delta = (card?.offsetWidth ?? 144) + 12;
    node.scrollBy({ left: direction * delta, behavior: "smooth" });
  }, []);

  if (options.length === 0) {
    return (
      <section className="rounded-jp-card border border-jp-border bg-jp-surface p-3.5" data-testid="standard-fare-details">
        <h3 className="text-sm font-semibold text-jp-text">Standard fare</h3>
        <p className="mt-1 text-sm text-jp-text-muted">This flight has one available fare. Review the supplied baggage, policy and price details below.</p>
      </section>
    );
  }

  const showNav = shouldUseFareCarousel(options.length);

  return (
    <section className="rounded-jp-card border border-jp-border bg-jp-surface p-3.5" data-testid="fare-family-details" aria-labelledby="fare-family-heading">
      <div className="flex items-end justify-between gap-3">
        <div>
          <h3 id="fare-family-heading" className="text-sm font-semibold text-jp-text">Choose a fare</h3>
          <p className="mt-1 text-xs text-jp-text-muted">Only supplier-returned fare differences are shown.</p>
        </div>
        <p className="shrink-0 text-xs text-jp-text-muted">{options.length} options</p>
      </div>
      <div className="relative mt-3">
        {showNav ? (
          <>
            <button
              type="button"
              className="absolute left-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-jp-border bg-jp-surface text-lg text-jp-text-muted shadow-sm hover:border-jp-primary hover:text-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary sm:inline-flex"
              aria-label="Previous fare options"
              onClick={() => scrollBy(-1)}
            >
              ‹
            </button>
            <button
              type="button"
              className="absolute right-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-jp-border bg-jp-surface text-lg text-jp-text-muted shadow-sm hover:border-jp-primary hover:text-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary sm:inline-flex"
              aria-label="Next fare options"
              onClick={() => scrollBy(1)}
            >
              ›
            </button>
          </>
        ) : null}
        <div className={cn(showNav && "sm:mx-10 sm:overflow-hidden")}>
          <div
            ref={scrollerRef}
            className="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory [scrollbar-width:thin]"
            role="list"
            aria-label="Fare family options"
          >
          {options.map((option) => {
            const selected = option.option_key === selectedKey;
            const selectable =
              option.selection_key_authoritative === true
              && option.can_select !== false
              && option.selectable !== false
              && option.is_synthetic_default !== true;
            const benefits = benefitRows(option);
            return (
              <div key={option.option_key} data-fare-family-card role="listitem" className="w-[88%] shrink-0 snap-start sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div
                  className={cn(
                    "flex h-full w-full flex-col rounded-jp-md border p-3.5 text-left text-sm transition-colors",
                    selected
                      ? "border-jp-primary bg-jp-primary/8 shadow-sm ring-2 ring-jp-primary/25"
                      : "border-jp-border bg-jp-surface hover:border-jp-primary/50",
                  )}
                  data-selected={selected ? "true" : "false"}
                  data-selectable={selectable ? "true" : "false"}
                >
                  <div className="flex items-start justify-between gap-2">
                    <p className="font-semibold text-jp-text">
                      <span
                        aria-hidden
                        className={cn(
                          "mr-2 inline-flex h-4 w-4 items-center justify-center rounded-full border text-[10px]",
                          selected ? "border-jp-primary bg-jp-primary text-white" : "border-jp-border bg-jp-surface",
                        )}
                      >
                        {selected ? "✓" : ""}
                      </span>
                      {option.brand_name ?? option.name ?? "Fare"}
                    </p>
                    {selected ? (
                      <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-semibold text-white">Selected</span>
                    ) : (
                      <span className={cn("rounded-full px-2 py-0.5 text-[11px] font-semibold", selectable ? "bg-jp-page text-jp-text-muted" : "bg-jp-page text-jp-text-muted")}>
                        {selectable ? "Select" : "Unavailable"}
                      </span>
                    )}
                  </div>
                  {formatWholePkr(option.displayed_price) ? (
                    <p className="mt-3 text-lg font-bold text-jp-text">{formatWholePkr(option.displayed_price)}</p>
                  ) : (
                    <p className="mt-3 text-xs text-jp-text-muted">Price not supplied</p>
                  )}
                  <dl className="mt-3 flex-1 divide-y divide-jp-border-soft text-xs">
                    {benefits.map((row) => (
                      <div key={row.label} className="grid grid-cols-[5.5rem_1fr] gap-2 py-1.5">
                        <dt className="text-jp-text-muted">{row.label}</dt>
                        <dd className="text-jp-text">{row.value}</dd>
                      </div>
                    ))}
                  </dl>
                  <button
                    type="button"
                    disabled={disabled || !selectable}
                    aria-pressed={selected}
                    onClick={() => onSelect(option.option_key)}
                    className="mt-auto w-full rounded-jp-md bg-jp-primary px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-jp-border disabled:text-jp-text-muted"
                  >
                    {selected ? "Selected" : selectable ? "Select fare" : "Not selectable"}
                  </button>
                  <button
                    type="button"
                    disabled={disabled || !selectable}
                    className="mt-2 w-full rounded-jp-md border border-jp-border px-3 py-2 text-sm font-medium text-jp-primary disabled:cursor-not-allowed disabled:text-jp-text-muted"
                    onClick={async () => {
                      await onViewDetails(option.option_key);
                      document.querySelector('[data-testid="fare-summary-tabs"]')?.scrollIntoView({ behavior: "smooth", block: "start" });
                    }}
                  >
                    View Details
                  </button>
                </div>
              </div>
            );
          })}
          </div>
        </div>
      </div>
    </section>
  );
}
