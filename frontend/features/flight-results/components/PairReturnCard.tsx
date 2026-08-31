"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo, useState } from "react";
import type { FareFamilyOption, FlightOffer, PairedReturnOption } from "../types";
import { normalizeJourneyDisplay } from "../utils/normalize-journey-display";
import { formatWholePkr } from "../utils/price";
import { AirlineIdentity } from "./AirlineIdentity";
import { FareBadge } from "./FareBadge";
import { FlightResultActions } from "./FlightResultActions";
import { SupplierSourceBadge } from "./SupplierSourceBadge";
import { TimeRouteBlock } from "./TimeRouteBlock";

type PairReturnCardProps = {
  option: PairedReturnOption;
  onSelect: (option: PairedReturnOption, fareOptionKey?: string, intent?: "details" | "booking") => void;
  onDetails?: (option: PairedReturnOption, fareOptionKey?: string, intent?: "details" | "booking") => void;
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

  const openFareConfirmation = (intent: "details" | "booking") => {
    const key =
      resolveAuthoritativeFareOptionKey(effectiveFareKey, fareOptions)
      ?? (effectiveFareKey.trim() !== "" ? effectiveFareKey : undefined);
    if (onDetails) {
      onDetails(option, key, intent);
      return;
    }
    onSelect(option, key, intent);
  };

  return (
    <article
      className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card transition-all hover:border-jp-primary/30 hover:shadow-md focus-within:border-jp-primary/40 sm:px-4"
      data-testid="pair-return-card"
      data-selected-fare-key={effectiveFareKey || undefined}
      aria-label={`Paired flight ${outbound.origin_airport_code} to ${outbound.destination_airport_code} return ${inbound.origin_airport_code} to ${inbound.destination_airport_code}`}
    >
      <div className="grid items-stretch gap-3 lg:grid-cols-[minmax(7.5rem,0.75fr)_minmax(0,2.2fr)_minmax(10.5rem,0.95fr)] lg:items-center lg:gap-4 xl:grid-cols-[minmax(9rem,0.85fr)_minmax(22rem,2.5fr)_minmax(12.5rem,0.95fr)]">
        <div className="min-w-0 space-y-2 lg:pr-1">
          <AirlineIdentity
            code={outbound.airline_code || option.airline_code}
            name={outbound.airline_name || option.airline_name}
            logoUrl={outbound.airline_logo_url}
            size="md"
          />
          {showReturnAirline ? (
            <div className="border-t border-jp-border-soft pt-2">
              <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-jp-text-muted">Return</p>
              <AirlineIdentity
                code={inbound.airline_code}
                name={inbound.airline_name}
                logoUrl={inbound.airline_logo_url}
                size="sm"
              />
            </div>
          ) : null}
        </div>

        <div className="min-w-0">
          <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-center md:gap-2">
            <div className="min-w-0">
              <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-jp-primary">Outbound</p>
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
                compact
              />
            </div>
            <div
              className="hidden h-10 w-px bg-jp-border-soft md:block"
              aria-hidden="true"
              data-testid="pair-leg-separator"
            />
            <div className="min-w-0 border-t border-jp-border-soft pt-2 md:border-t-0 md:pt-0">
              <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-jp-primary">Return</p>
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
                compact
              />
            </div>
          </div>
          <div className="mt-2 flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <FareBadge refundable={option.refundable} />
            <SupplierSourceBadge label={option.supplier_source_label} />
          </div>
        </div>

        <div className="flex min-w-0 flex-wrap items-end justify-between gap-3 border-t border-jp-border-soft pt-3 lg:h-full lg:min-w-[10.5rem] lg:flex-col lg:items-end lg:justify-center lg:border-l lg:border-t-0 lg:pl-3 lg:pt-0 xl:min-w-[12.5rem] xl:pl-4">
          <div className="min-w-0 text-left sm:text-right">
            <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">Total fare</p>
            <p className="text-lg font-bold leading-tight text-jp-text break-words" data-testid="result-price-display">
              {displayPrice}
            </p>
          </div>
          <FlightResultActions
            onDetails={() => openFareConfirmation("details")}
            onBook={() => openFareConfirmation("booking")}
            canBook={option.can_book !== false}
            booking={Boolean(selecting)}
            detailsTestId="pair-details"
            bookTestId="pair-select"
          />
        </div>
      </div>
    </article>
  );
}
