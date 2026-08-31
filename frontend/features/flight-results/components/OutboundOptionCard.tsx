"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo, useState } from "react";
import type { FareFamilyOption, FlightOffer, OutboundOption } from "../types";
import { normalizeJourneyDisplay } from "../utils/normalize-journey-display";
import { formatWholePkr } from "../utils/price";
import { AirlineIdentity } from "./AirlineIdentity";
import { FareBadge } from "./FareBadge";
import { FlightResultActions } from "./FlightResultActions";
import { SupplierSourceBadge } from "./SupplierSourceBadge";
import { TimeRouteBlock } from "./TimeRouteBlock";

type OutboundOptionCardProps = {
  option: OutboundOption;
  searchId: string;
  onOpenDetails?: (
    option: OutboundOption,
    offer: FlightOffer,
    fareOptionKey: string,
    intent: "details" | "booking",
  ) => void;
};

function resolveFareOptions(option: OutboundOption): FareFamilyOption[] {
  return option.branded_fares_display_options ?? option.fare_family_options_display ?? [];
}

function optionAirlineFallback(option: OutboundOption) {
  const extended = option as OutboundOption & {
    airline_code?: string;
    airline_name?: string;
    airline_logo_url?: string | null;
  };
  return {
    airline_code: extended.airline_code,
    airline_name: extended.airline_name,
    airline_logo_url: extended.airline_logo_url ?? null,
  };
}

export function outboundOptionToOffer(option: OutboundOption): FlightOffer {
  const journey = normalizeJourneyDisplay(
    option.journey_display as Record<string, unknown> | undefined,
    optionAirlineFallback(option),
  );
  return {
    offer_id: option.outbound_key,
    airline_code: journey.airline_code,
    airline_name: journey.airline_name,
    airline_logo_url: journey.airline_logo_url,
    departure_time: journey.departure_time_display,
    arrival_time: journey.arrival_time_display,
    departure_airport_code: journey.origin_airport_code,
    arrival_airport_code: journey.destination_airport_code,
    duration: journey.duration_display,
    stops: journey.stops,
    stops_label_display: journey.stops_label_display,
    layover_summary_display: journey.layover_summary_display,
    arrival_day_offset_display: journey.arrival_day_offset_display,
    displayed_price: option.from_total_amount,
    price_display: option.from_total_display,
    final_customer_price: option.from_total_amount,
    supplier_source_label: option.supplier_source_label,
    can_book: true,
    branded_fares_display_options: option.branded_fares_display_options,
    fare_family_options_display: option.fare_family_options_display,
    has_branded_fares: option.has_branded_fares,
    has_fare_choice_options: option.has_fare_choice_options,
    segments: [
      {
        origin_airport_code: journey.origin_airport_code,
        destination_airport_code: journey.destination_airport_code,
        departure_time_display: journey.departure_time_display,
        arrival_time_display: journey.arrival_time_display,
        duration_display: journey.duration_display,
        airline_code: journey.airline_code,
        airline_name: journey.airline_name,
        airline_logo_url: journey.airline_logo_url,
        arrival_day_offset_display: journey.arrival_day_offset_display,
      },
    ],
  };
}

export function OutboundOptionCard({ option, searchId, onOpenDetails }: OutboundOptionCardProps) {
  const journey = normalizeJourneyDisplay(
    option.journey_display as Record<string, unknown> | undefined,
    optionAirlineFallback(option),
  );
  const fareOptions = useMemo(() => resolveFareOptions(option), [option]);
  const [selectedFareKey] = useState(() => fareOptions[0]?.option_key ?? "");
  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey) ?? fareOptions[0];
  const effectiveFareKey = selectedOption?.option_key ?? selectedFareKey;
  const displayPrice = formatWholePkr(selectedOption?.displayed_price ?? option.from_total_amount);

  const openWithSelectedFare = (intent: "details" | "booking") => {
    const offer = outboundOptionToOffer(option);
    const fareKeyForDetails = resolveAuthoritativeFareOptionKey(effectiveFareKey, fareOptions) ?? effectiveFareKey ?? "";
    onOpenDetails?.(option, offer, fareKeyForDetails, intent);
  };

  return (
    <article
      className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card transition-all hover:border-jp-primary/30 hover:shadow-md focus-within:border-jp-primary/40 sm:px-4"
      data-testid="outbound-option-card"
      data-selected-fare-key={effectiveFareKey || undefined}
      aria-label={`${journey.airline_name || journey.airline_code || "Flight"} ${journey.departure_time_display} to ${journey.arrival_time_display}`}
    >
      <div className="grid items-stretch gap-3 lg:grid-cols-[minmax(8rem,0.85fr)_minmax(0,2fr)_minmax(10.5rem,0.95fr)] lg:items-center lg:gap-4 xl:grid-cols-[minmax(10.5rem,1fr)_minmax(20rem,2.35fr)_minmax(12.5rem,0.95fr)]">
        <div className="min-w-0 lg:pr-1">
          <AirlineIdentity
            code={journey.airline_code}
            name={journey.airline_name}
            logoUrl={journey.airline_logo_url}
            size="md"
          />
          {option.combo_count ? (
            <p className="mt-1 text-xs text-jp-text-muted">
              {option.combo_count} return option{option.combo_count === 1 ? "" : "s"}
            </p>
          ) : null}
        </div>

        <div className="min-w-0 space-y-2">
          <TimeRouteBlock
            departureTime={journey.departure_time_display}
            arrivalTime={journey.arrival_time_display}
            arrivalDayOffset={journey.arrival_day_offset_display}
            originCode={journey.origin_airport_code}
            destinationCode={journey.destination_airport_code}
            duration={journey.duration_display}
            stops={journey.stops}
            stopsLabel={journey.stops_label_display}
            layoverSummary={journey.layover_summary_display}
          />
          <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <FareBadge />
            <SupplierSourceBadge label={option.supplier_source_label} />
          </div>
        </div>

        <div className="flex min-w-0 flex-wrap items-end justify-between gap-3 border-t border-jp-border-soft pt-3 lg:h-full lg:min-w-[10.5rem] lg:flex-col lg:items-end lg:justify-center lg:border-l lg:border-t-0 lg:pl-3 lg:pt-0 xl:min-w-[12.5rem] xl:pl-4">
          <div className="min-w-0 text-left sm:text-right">
            <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">From total return fare</p>
            <p className="text-lg font-bold leading-tight text-jp-text break-words" data-testid="result-price-display">
              {displayPrice ?? option.from_total_display ?? "Price unavailable"}
            </p>
          </div>
          <FlightResultActions
            onDetails={() => openWithSelectedFare("details")}
            onBook={() => openWithSelectedFare("booking")}
            detailsTestId="outbound-details-trigger"
            bookTestId="outbound-book-now"
          />
        </div>
      </div>
      <span className="sr-only">{searchId}</span>
    </article>
  );
}
