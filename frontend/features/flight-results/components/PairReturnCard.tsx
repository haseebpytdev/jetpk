"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo, useState } from "react";
import type { FareFamilyOption, PairedReturnOption } from "../types";
import { formatWholePkr } from "../utils/price";
import { AirlineIdentity } from "./AirlineIdentity";
import { FareBadge } from "./FareBadge";
import { SupplierSourceBadge } from "./SupplierSourceBadge";
import { TimeRouteBlock } from "./TimeRouteBlock";

type PairReturnCardProps = {
  option: PairedReturnOption;
  onSelect: (option: PairedReturnOption, fareOptionKey?: string) => void;
  onDetails?: (option: PairedReturnOption, fareOptionKey?: string) => void;
  selecting?: boolean;
};

function journeyMeta(journey?: Record<string, unknown>) {
  return {
    dep: String(journey?.departure_time_display ?? journey?.departure_time ?? "—"),
    arr: String(journey?.arrival_time_display ?? journey?.arrival_time ?? "—"),
    origin: String(journey?.origin_airport_code ?? journey?.origin ?? "—"),
    dest: String(journey?.destination_airport_code ?? journey?.destination ?? "—"),
    duration: String(journey?.duration_display ?? journey?.duration ?? ""),
    stops: Number(journey?.stops ?? 0),
    stopsLabel: String(journey?.stops_label_display ?? ""),
    layoverSummary: Array.isArray(journey?.layover_summary_display)
      ? (journey?.layover_summary_display as string[])
      : undefined,
    offset: typeof journey?.arrival_day_offset_display === "string" ? journey.arrival_day_offset_display : undefined,
    airlineCode: String(journey?.airline_code ?? ""),
    airlineName: String(journey?.airline_name ?? ""),
    airlineLogo: (journey?.airline_logo_url as string | null | undefined) ?? null,
    flightNumber: String(journey?.flight_number ?? ""),
  };
}

function resolveFareOptions(option: PairedReturnOption): FareFamilyOption[] {
  return option.branded_fares_display_options ?? option.fare_family_options_display ?? [];
}

export function PairReturnCard({ option, onSelect, onDetails, selecting }: PairReturnCardProps) {
  const outbound = journeyMeta(option.outbound_journey);
  const inbound = journeyMeta(option.return_journey);
  const fareOptions = useMemo(() => resolveFareOptions(option), [option]);
  const [selectedFareKey] = useState(() => fareOptions[0]?.option_key ?? "");
  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey) ?? fareOptions[0];
  const effectiveFareKey = selectedOption?.option_key ?? selectedFareKey;
  const displayPrice = formatWholePkr(selectedOption?.displayed_price ?? option.total_amount) ?? option.total_display ?? "Fare unavailable";

  const openFareConfirmation = (intent: "details" | "booking") => {
    const key =
      resolveAuthoritativeFareOptionKey(effectiveFareKey, fareOptions)
      ?? (effectiveFareKey.trim() !== "" ? effectiveFareKey : undefined);
    if (onDetails) {
      onDetails(option, key);
      return;
    }
    onSelect(option, key);
  };

  return (
    <article
      className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card transition-all hover:border-jp-primary/30 hover:shadow-md focus-within:border-jp-primary/40 sm:px-4"
      data-testid="pair-return-card"
      data-selected-fare-key={effectiveFareKey || undefined}
      aria-label={`Paired flight ${outbound.origin} to ${outbound.dest} return ${inbound.origin} to ${inbound.dest}`}
    >
      <div className="grid items-start gap-3 md:grid-cols-[minmax(8rem,0.85fr)_minmax(16rem,2fr)_minmax(10.5rem,0.95fr)] lg:gap-4 xl:grid-cols-[minmax(10.5rem,1fr)_minmax(20rem,2.35fr)_minmax(12.5rem,0.95fr)]">
        <div className="min-w-0 space-y-3 md:pr-1">
          <div>
            <AirlineIdentity
              code={outbound.airlineCode || option.airline_code}
              name={outbound.airlineName || option.airline_name}
              logoUrl={outbound.airlineLogo}
              size="md"
            />
            {outbound.flightNumber ? (
              <p className="mt-1 truncate text-xs text-jp-text-muted">{outbound.flightNumber}</p>
            ) : null}
          </div>
        </div>

        <div className="min-w-0 space-y-4">
          <div>
            <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-jp-primary">Outbound</p>
            <TimeRouteBlock
              departureTime={outbound.dep}
              arrivalTime={outbound.arr}
              originCode={outbound.origin}
              destinationCode={outbound.dest}
              duration={outbound.duration}
              arrivalDayOffset={outbound.offset}
              stops={outbound.stops}
              stopsLabel={outbound.stopsLabel || undefined}
              layoverSummary={outbound.layoverSummary}
            />
          </div>
          <div>
            <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-jp-primary">Return</p>
            <TimeRouteBlock
              departureTime={inbound.dep}
              arrivalTime={inbound.arr}
              originCode={inbound.origin}
              destinationCode={inbound.dest}
              duration={inbound.duration}
              arrivalDayOffset={inbound.offset}
              stops={inbound.stops}
              stopsLabel={inbound.stopsLabel || undefined}
              layoverSummary={inbound.layoverSummary}
            />
          </div>
          <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <FareBadge refundable={option.refundable} />
            <SupplierSourceBadge label={option.supplier_source_label} />
          </div>
        </div>

        <div className="flex min-w-0 items-end justify-between gap-3 border-t border-jp-border-soft pt-3 md:h-full md:min-w-[10.5rem] md:flex-col md:items-end md:justify-center md:border-l md:border-t-0 md:pl-3 md:pt-0 xl:min-w-[12.5rem] xl:pl-4">
          <div className="text-right">
            <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">Total fare</p>
            <p className="whitespace-nowrap text-lg font-bold text-jp-text" data-testid="result-price-display">
              {displayPrice}
            </p>
          </div>
          <div className="flex flex-col items-end gap-2">
            <button
              type="button"
              className="text-sm font-medium text-jp-primary underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
              data-testid="pair-details"
              onClick={() => openFareConfirmation("details")}
            >
              Details
            </button>
            <button
              type="button"
              className="rounded-jp-md bg-jp-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
              disabled={!option.can_book || selecting}
              onClick={() => openFareConfirmation("booking")}
              data-testid="pair-select"
            >
              {selecting ? "…" : "Book Now"}
            </button>
          </div>
        </div>
      </div>
    </article>
  );
}
