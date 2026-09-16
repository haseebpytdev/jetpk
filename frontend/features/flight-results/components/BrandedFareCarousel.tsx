"use client";

import { cn } from "@/lib/cn";
import { shouldUseFareCarousel } from "@/lib/fare-selection-authority";
import { useCallback, useRef } from "react";
import type { FareFamilyOption } from "../types";
import { formatDisplayPrice } from "../utils/price";
import { PriceBlock } from "./PriceBlock";

type BrandedFareCarouselProps = {
  options: FareFamilyOption[];
  selectedKey?: string;
  onSelect: (optionKey: string) => void;
  onBook: (optionKey: string) => void;
  bookingOptionKey?: string | null;
  disabled?: boolean;
};

export function BrandedFareCarousel({
  options,
  selectedKey,
  onSelect,
  onBook,
  bookingOptionKey,
  disabled,
}: BrandedFareCarouselProps) {
  const scrollerRef = useRef<HTMLDivElement>(null);

  const scrollBy = useCallback((direction: -1 | 1) => {
    const node = scrollerRef.current;
    if (!node) return;
    const card = node.querySelector<HTMLElement>("[data-fare-card]");
    const delta = (card?.offsetWidth ?? 220) + 12;
    node.scrollBy({ left: direction * delta, behavior: "smooth" });
  }, []);

  if (!options.length) return null;

  const showNav = shouldUseFareCarousel(options.length);

  return (
    <div className="mt-3 border-t border-jp-border-soft pt-3" data-testid="branded-fare-carousel">
      <p className="mb-2 text-xs font-medium text-jp-text-muted">Choose fare family</p>
      <div className="relative">
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
            "flex gap-3 overflow-x-auto pb-1 snap-x snap-mandatory",
            showNav && "px-1 sm:px-10",
          )}
          role="list"
          aria-label="Fare family options"
        >
          {options.map((option) => {
            const isSelected = selectedKey === option.option_key;
            const amount = option.displayed_price ?? null;
            return (
              <div
                key={option.option_key}
                data-fare-card
                role="listitem"
                className={cn(
                  "min-w-[16rem] max-w-[18rem] shrink-0 snap-start rounded-jp-md border p-3",
                  isSelected ? "border-jp-primary bg-jp-primary-soft" : "border-jp-border bg-jp-surface",
                )}
              >
                <button
                  type="button"
                  className="w-full text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary rounded-jp-sm"
                  onClick={() => onSelect(option.option_key)}
                  aria-pressed={isSelected}
                >
                  <p className="text-sm font-semibold text-jp-text">{option.name ?? option.brand_name ?? "Fare"}</p>
                  {isSelected ? <p className="text-[10px] font-medium uppercase text-jp-primary">Selected</p> : null}
                  {option.baggage ? <p className="mt-1 text-xs text-jp-text-muted">Checked: {option.baggage}</p> : <p className="mt-1 text-xs text-jp-text-muted">Checked: Not specified</p>}
                  {option.meal ? <p className="text-xs text-jp-text-muted">Meal: {option.meal}</p> : <p className="text-xs text-jp-text-muted">Meal: Airline policy</p>}
                  {option.refund_rule ? <p className="text-xs text-jp-text-muted">Refund: {option.refund_rule}</p> : <p className="text-xs text-jp-text-muted">Refund: Airline policy</p>}
                  {option.change_rule ? <p className="text-xs text-jp-text-muted">Changes: {option.change_rule}</p> : <p className="text-xs text-jp-text-muted">Changes: Airline policy</p>}
                  {option.seat_selection ? <p className="text-xs text-jp-text-muted">Seat: {option.seat_selection}</p> : null}
                </button>
                <div className="mt-2">
                  <PriceBlock
                    amount={amount}
                    priceDisplay={option.price_display}
                    disabled={disabled}
                    loading={bookingOptionKey === option.option_key}
                    onSelect={() => onBook(option.option_key)}
                    className="w-full min-w-0"
                    testId={`fare-price-${option.option_key}`}
                  />
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
