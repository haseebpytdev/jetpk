"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo, useState } from "react";
import type { FareFamilyOption, PairedReturnOption } from "../types";
import { BrandedFareCarousel } from "./BrandedFareCarousel";
import { SupplierSourceBadge } from "./SupplierSourceBadge";
import { TimeRouteBlock } from "./TimeRouteBlock";

type PairReturnCardProps = {
  option: PairedReturnOption;
  onSelect: (option: PairedReturnOption, fareOptionKey?: string) => void;
  onDetails?: (option: PairedReturnOption, fareOptionKey?: string) => void;
  selecting?: boolean;
};

function journeyTimes(journey?: Record<string, unknown>) {
  return {
    dep: String(journey?.departure_time_display ?? journey?.departure_time ?? "—"),
    arr: String(journey?.arrival_time_display ?? journey?.arrival_time ?? "—"),
    origin: String(journey?.origin_airport_code ?? journey?.origin ?? "—"),
    dest: String(journey?.destination_airport_code ?? journey?.destination ?? "—"),
    duration: String(journey?.duration_display ?? journey?.duration ?? ""),
    stops: String(journey?.stops_label_display ?? ""),
    offset: typeof journey?.arrival_day_offset_display === "string" ? journey.arrival_day_offset_display : undefined,
  };
}

function resolveFareOptions(option: PairedReturnOption): FareFamilyOption[] {
  return option.branded_fares_display_options ?? option.fare_family_options_display ?? [];
}

export function PairReturnCard({ option, onSelect, onDetails, selecting }: PairReturnCardProps) {
  const outbound = journeyTimes(option.outbound_journey);
  const inbound = journeyTimes(option.return_journey);
  const fareOptions = useMemo(() => resolveFareOptions(option), [option]);
  const [selectedFareKey, setSelectedFareKey] = useState(() => fareOptions[0]?.option_key ?? "");
  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey) ?? fareOptions[0];
  const effectiveFareKey = selectedOption?.option_key ?? selectedFareKey;
  const priceLabel = selectedOption?.price_display ?? option.total_display ?? "Fare unavailable";
  const refundLabel = option.refundable === true ? "Refundable" : option.refundable === false ? "Non-refundable" : null;

  const openFareConfirmation = (fareKey?: string) => {
    const key = resolveAuthoritativeFareOptionKey(fareKey ?? effectiveFareKey, fareOptions) ?? fareKey ?? effectiveFareKey;
    if (onDetails) {
      onDetails(option, key || undefined);
      return;
    }
    onSelect(option, key || undefined);
  };

  return (
    <article
      className="rounded-jp-card border border-jp-border bg-jp-surface p-4"
      data-testid="pair-return-card"
      data-selected-fare-key={effectiveFareKey || undefined}
    >
      <p className="text-xs font-medium uppercase tracking-wide text-jp-text-muted">Outbound</p>
      <TimeRouteBlock
        departureTime={outbound.dep}
        arrivalTime={outbound.arr}
        originCode={outbound.origin}
        destinationCode={outbound.dest}
        duration={outbound.duration}
        arrivalDayOffset={outbound.offset}
      />
      {outbound.stops ? <p className="mt-1 text-xs text-jp-text-muted">{outbound.stops}</p> : null}

      <p className="mt-3 text-xs font-medium uppercase tracking-wide text-jp-text-muted">Return</p>
      <TimeRouteBlock
        departureTime={inbound.dep}
        arrivalTime={inbound.arr}
        originCode={inbound.origin}
        destinationCode={inbound.dest}
        duration={inbound.duration}
        arrivalDayOffset={inbound.offset}
      />
      {inbound.stops ? <p className="mt-1 text-xs text-jp-text-muted">{inbound.stops}</p> : null}

      <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="text-lg font-semibold text-jp-text">{priceLabel}</p>
          <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-jp-text-muted">
            {[option.airline_name, option.cabin, option.baggage].filter(Boolean).join(" · ") ? (
              <span>{[option.airline_name, option.cabin, option.baggage].filter(Boolean).join(" · ")}</span>
            ) : null}
            {refundLabel ? <span data-testid="pair-refundability">{refundLabel}</span> : null}
            <SupplierSourceBadge label={option.supplier_source_label} />
          </div>
        </div>
        <div className="flex gap-2">
          {onDetails ? (
            <button
              type="button"
              className="rounded-jp-md border border-jp-border px-3 py-2 text-sm"
              data-testid="pair-details"
              onClick={() => openFareConfirmation()}
            >
              Details
            </button>
          ) : null}
          <button
            type="button"
            className="rounded-jp-md bg-jp-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-60"
            disabled={!option.can_book || selecting}
            onClick={() => openFareConfirmation()}
            data-testid="pair-select"
          >
            {selecting ? "…" : "Book Now"}
          </button>
        </div>
      </div>

      {fareOptions.length > 1 ? (
        <BrandedFareCarousel
          options={fareOptions}
          selectedKey={effectiveFareKey}
          onSelect={setSelectedFareKey}
          onBook={(optionKey) => {
            setSelectedFareKey(optionKey);
            openFareConfirmation(optionKey);
          }}
          bookingOptionKey={selecting ? effectiveFareKey : null}
          disabled={!option.can_book}
        />
      ) : null}
    </article>
  );
}
