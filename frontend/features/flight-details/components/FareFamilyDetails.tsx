"use client";

import { cn } from "@/lib/cn";
import { shouldUseFareCarousel } from "@/lib/fare-selection-authority";
import { useCallback, useRef } from "react";
import type { FareFamilyOption } from "../types";

type FareFamilyDetailsProps = {
  options: FareFamilyOption[];
  selectedKey: string;
  onSelect: (key: string) => void;
  disabled?: boolean;
};

export function FareFamilyDetails({ options, selectedKey, onSelect, disabled }: FareFamilyDetailsProps) {
  const scrollerRef = useRef<HTMLDivElement>(null);

  const scrollBy = useCallback((direction: -1 | 1) => {
    const node = scrollerRef.current;
    if (!node) return;
    const card = node.querySelector<HTMLElement>("[data-fare-family-card]");
    const delta = (card?.offsetWidth ?? 144) + 8;
    node.scrollBy({ left: direction * delta, behavior: "smooth" });
  }, []);

  if (options.length === 0) {
    return (
      <section className="rounded-jp-card border border-jp-border bg-jp-surface p-4" data-testid="standard-fare-details">
        <h3 className="text-sm font-semibold text-jp-text">Standard fare</h3>
        <p className="mt-1 text-sm text-jp-text-muted">This flight has one available fare. Review the supplied baggage, policy and price details below.</p>
      </section>
    );
  }

  const showNav = shouldUseFareCarousel(options.length);

  return (
    <section className="rounded-jp-card border border-jp-border bg-jp-surface p-4" data-testid="fare-family-details" aria-labelledby="fare-family-heading">
      <div className="flex items-end justify-between gap-3">
        <div>
          <h3 id="fare-family-heading" className="text-sm font-semibold text-jp-text">Choose a fare</h3>
          <p className="mt-1 text-xs text-jp-text-muted">Only supplier-returned fare differences are shown.</p>
        </div>
        <p className="shrink-0 text-xs text-jp-text-muted">{options.length} options</p>
      </div>
      <div className="relative mt-2">
        {showNav ? (
          <>
            <button
              type="button"
              className="absolute left-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 rounded-full border border-jp-border bg-jp-surface shadow-jp-card sm:inline-flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
              aria-label="Previous fare options"
              onClick={() => scrollBy(-1)}
            >
              ‹
            </button>
            <button
              type="button"
              className="absolute right-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 rounded-full border border-jp-border bg-jp-surface shadow-jp-card sm:inline-flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
              aria-label="Next fare options"
              onClick={() => scrollBy(1)}
            >
              ›
            </button>
          </>
        ) : null}
        <div
          ref={scrollerRef}
          className={cn(
            "flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory [scrollbar-width:thin]",
            showNav && "px-1 sm:px-10",
          )}
          role="list"
          aria-label="Fare family options"
        >
          {options.map((option) => {
            const selected = option.option_key === selectedKey;
            return (
              <div key={option.option_key} data-fare-family-card role="listitem" className="w-[86%] shrink-0 snap-start sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <button
                  type="button"
                  disabled={disabled}
                  aria-pressed={selected}
                  onClick={() => onSelect(option.option_key)}
                  className={cn(
                    "h-full w-full rounded-jp-md border p-4 text-left text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary",
                    selected ? "border-jp-primary bg-jp-primary/5 shadow-jp-card" : "border-jp-border bg-jp-surface hover:border-jp-primary/50",
                  )}
                >
                  <div className="flex items-start justify-between gap-2">
                    <p className="font-semibold text-jp-text">{option.brand_name ?? option.name ?? "Fare"}</p>
                    <span className={cn("rounded-full px-2 py-0.5 text-[11px] font-semibold", selected ? "bg-jp-primary text-white" : "bg-jp-page text-jp-text-muted")}>{selected ? "Selected" : "Select"}</span>
                  </div>
                  {option.price_display ? <p className="mt-3 text-lg font-bold text-jp-text">{option.price_display}</p> : <p className="mt-3 text-xs text-jp-text-muted">Price not supplied</p>}
                  <dl className="mt-3 space-y-1.5 text-xs text-jp-text-muted">
                    {option.baggage ? <div><dt className="sr-only">Baggage</dt><dd>Bag: {option.baggage}</dd></div> : null}
                    {option.refund_rule ? <div><dt className="sr-only">Refundability</dt><dd>{option.refund_rule}</dd></div> : null}
                    {option.change_rule ? <div><dt className="sr-only">Changes</dt><dd>{option.change_rule}</dd></div> : null}
                    {option.meal ? <div><dt className="sr-only">Meal</dt><dd>Meal: {option.meal}</dd></div> : null}
                    {option.seat_selection ? <div><dt className="sr-only">Seat selection</dt><dd>Seats: {option.seat_selection}</dd></div> : null}
                  </dl>
                </button>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
