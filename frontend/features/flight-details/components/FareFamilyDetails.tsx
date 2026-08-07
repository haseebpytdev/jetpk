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

  if (options.length <= 1) return null;

  const showNav = shouldUseFareCarousel(options.length);

  return (
    <section data-testid="fare-family-details" aria-labelledby="fare-family-heading">
      <h3 id="fare-family-heading" className="text-sm font-semibold text-jp-text">
        Compare fare families
      </h3>
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
            "flex gap-2 overflow-x-auto pb-1 snap-x snap-mandatory",
            showNav && "px-1 sm:px-10",
          )}
          role="list"
          aria-label="Fare family options"
        >
          {options.map((option) => {
            const selected = option.option_key === selectedKey;
            return (
              <div key={option.option_key} data-fare-family-card role="listitem" className="shrink-0 snap-start">
                <button
                  type="button"
                  disabled={disabled}
                  aria-pressed={selected}
                  onClick={() => onSelect(option.option_key)}
                  className={cn(
                    "min-w-[9rem] max-w-[11rem] rounded-jp-md border px-3 py-2 text-left text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary",
                    selected ? "border-jp-primary bg-jp-primary/5" : "border-jp-border bg-jp-surface",
                  )}
                >
                  <p className="font-medium text-jp-text">{option.brand_name ?? option.name ?? "Fare"}</p>
                  {option.price_display ? <p className="text-jp-text-muted">{option.price_display}</p> : null}
                  {option.baggage ? <p className="mt-1 text-xs text-jp-text-muted">{option.baggage}</p> : null}
                  {option.refund_rule ? <p className="text-xs text-jp-text-muted">{option.refund_rule}</p> : null}
                  {option.change_rule ? <p className="text-xs text-jp-text-muted">{option.change_rule}</p> : null}
                </button>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
