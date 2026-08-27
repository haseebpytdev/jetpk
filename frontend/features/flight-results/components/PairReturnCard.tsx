"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo, useState } from "react";
import type { FareFamilyOption, FlightOffer, PairedReturnOption } from "../types";
import { normalizeJourneyDisplay } from "../utils/normalize-journey-display";
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

function resolveFareOptions(option: PairedReturnOption): FareFamilyOption[] {
  return option.branded_fares_display_options ?? option.fare_family_options_display ?? [];
}

function journeyFallback(option: PairedReturnOption) {
  return {
    airline_code: option.airline_code,
    airline_name: option.airline_name,
    airline_logo_url: (option as PairedReturnOption & { airline_logo_url?: string | null }).airline_logo_url ?? null,
  };
}

/** Seed drawer/details from paired card so journey/fare tabs survive slow or partial offer details. */
export function pairedOptionToOffer(option: PairedReturnOption): FlightOffer {
  const outbound = normalizeJourneyDisplay(option.outbound_journey, journeyFallback(option));
  const inbound = normalizeJourneyDisplay(option.return_journey, journeyFallback(option));
  const fareOptions = resolveFareOptions(option);
  return {
    offer_id: option.combo_id,
    airline_code: outbound.airline_code || option.airline_code,
    airline_name: outbound.airline_name || option.airline_name,
    airline_logo_url: outbound.airline_logo_url,
    departure_time: outbound.departure_time_display,
    arrival_time: outbound.arrival_time_display,
    departure_airport_code: outbound.origin_airport_code,
    arrival_airport_code: outbound.destination_airport_code,
    route:
      outbound.origin_airport_code && outbound.destination_airport_code
        ? `${outbound.origin_airport_code} → ${outbound.destination_airport_code}`
        : undefined,
    duration: outbound.duration_display,
    stops: outbound.stops,
    stops_label_display: outbound.stops_label_display,
    layover_summary_display: outbound.layover_summary_display,
    arrival_day_offset_display: outbound.arrival_day_offset_display,
    displayed_price: option.total_amount ?? undefined,
    price_display: option.total_display,
    final_customer_price: option.total_amount ?? undefined,
    supplier_source_label: option.supplier_source_label,
    can_book: option.can_book !== false,
    refundable: option.refundable,
    branded_fares_display_options: fareOptions,
    fare_family_options_display: fareOptions,
    has_branded_fares: option.has_branded_fares,
    has_fare_choice_options: option.has_fare_choice_options,
    segments: [
      {
        origin_airport_code: outbound.origin_airport_code,
        destination_airport_code: outbound.destination_airport_code,
        departure_time_display: outbound.departure_time_display,
        arrival_time_display: outbound.arrival_time_display,
        duration_display: outbound.duration_display,
        airline_code: outbound.airline_code,
        airline_name: outbound.airline_name,
        airline_logo_url: outbound.airline_logo_url,
        flight_number: outbound.flight_number,
        arrival_day_offset_display: outbound.arrival_day_offset_display,
      },
      {
        origin_airport_code: inbound.origin_airport_code,
        destination_airport_code: inbound.destination_airport_code,
        departure_time_display: inbound.departure_time_display,
        arrival_time_display: inbound.arrival_time_display,
        duration_display: inbound.duration_display,
        airline_code: inbound.airline_code || option.airline_code,
        airline_name: inbound.airline_name || option.airline_name,
        airline_logo_url: inbound.airline_logo_url,
        flight_number: inbound.flight_number,
        arrival_day_offset_display: inbound.arrival_day_offset_display,
      },
    ],
  };
}

export function PairReturnCard({ option, onSelect, onDetails, selecting }: PairReturnCardProps) {
  const fallback = journeyFallback(option);
  const outbound = normalizeJourneyDisplay(option.outbound_journey, fallback);
  const inbound = normalizeJourneyDisplay(option.return_journey, fallback);
  const fareOptions = useMemo(() => resolveFareOptions(option), [option]);
  const [selectedFareKey] = useState(() => fareOptions[0]?.option_key ?? "");
  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey) ?? fareOptions[0];
  const effectiveFareKey = selectedOption?.option_key ?? selectedFareKey;
  const displayPrice = formatWholePkr(selectedOption?.displayed_price ?? option.total_amount) ?? option.total_display ?? "Fare unavailable";
  const showReturnAirline =
    Boolean(inbound.airline_code) &&
    inbound.airline_code !== outbound.airline_code &&
    inbound.airline_code !== (option.airline_code ?? "");

  const openFareConfirmation = () => {
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
      aria-label={`Paired flight ${outbound.origin_airport_code} to ${outbound.destination_airport_code} return ${inbound.origin_airport_code} to ${inbound.destination_airport_code}`}
    >
      <div className="grid items-start gap-3 md:grid-cols-[minmax(8rem,0.85fr)_minmax(16rem,2fr)_minmax(10.5rem,0.95fr)] lg:gap-4 xl:grid-cols-[minmax(10.5rem,1fr)_minmax(20rem,2.35fr)_minmax(12.5rem,0.95fr)]">
        <div className="min-w-0 space-y-3 md:pr-1">
          <div>
            <AirlineIdentity
              code={outbound.airline_code || option.airline_code}
              name={outbound.airline_name || option.airline_name}
              logoUrl={outbound.airline_logo_url}
              size="md"
            />
            {outbound.flight_number ? (
              <p className="mt-1 truncate text-xs text-jp-text-muted">{outbound.flight_number}</p>
            ) : null}
          </div>
          {showReturnAirline ? (
            <div>
              <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-jp-text-muted">Return airline</p>
              <AirlineIdentity
                code={inbound.airline_code}
                name={inbound.airline_name}
                logoUrl={inbound.airline_logo_url}
                size="sm"
              />
            </div>
          ) : null}
        </div>

        <div className="min-w-0 space-y-4">
          <div>
            <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-jp-primary">Outbound</p>
            <TimeRouteBlock
              departureTime={outbound.departure_time_display}
              arrivalTime={outbound.arrival_time_display}
              originCode={outbound.origin_airport_code}
              destinationCode={outbound.destination_airport_code}
              duration={outbound.duration_display}
              arrivalDayOffset={outbound.arrival_day_offset_display}
              stops={outbound.stops}
              stopsLabel={outbound.stops_label_display || undefined}
              layoverSummary={outbound.layover_summary_display}
            />
          </div>
          <div>
            <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-jp-primary">Return</p>
            <TimeRouteBlock
              departureTime={inbound.departure_time_display}
              arrivalTime={inbound.arrival_time_display}
              originCode={inbound.origin_airport_code}
              destinationCode={inbound.destination_airport_code}
              duration={inbound.duration_display}
              arrivalDayOffset={inbound.arrival_day_offset_display}
              stops={inbound.stops}
              stopsLabel={inbound.stops_label_display || undefined}
              layoverSummary={inbound.layover_summary_display}
            />
          </div>
          <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <FareBadge refundable={option.refundable} />
            <SupplierSourceBadge label={option.supplier_source_label} />
          </div>
        </div>

        <div className="flex min-w-0 items-end justify-between gap-3 border-t border-jp-border-soft pt-3 md:h-full md:min-w-[10.5rem] md:flex-col md:items-end md:justify-center md:border-l md:border-t-0 md:pl-3 md:pt-0 xl:min-w-[12.5rem] xl:pl-4">
          <div className="text-right">
            <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">Total return fare</p>
            <p className="whitespace-nowrap text-lg font-bold text-jp-text" data-testid="result-price-display">
              {displayPrice}
            </p>
          </div>
          <div className="flex flex-col items-end gap-2">
            <button
              type="button"
              className="text-sm font-medium text-jp-primary underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
              data-testid="pair-details"
              onClick={() => openFareConfirmation()}
            >
              Details
            </button>
            <button
              type="button"
              className="rounded-jp-md bg-jp-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
              disabled={!option.can_book || selecting}
              onClick={() => openFareConfirmation()}
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
