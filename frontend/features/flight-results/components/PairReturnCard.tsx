"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo, useState } from "react";
import type { FareFamilyOption, FlightOffer, PairedReturnOption } from "../types";
import { normalizeJourneyDisplay } from "../utils/normalize-journey-display";
import { formatWholePkr } from "../utils/price";
import { AirlineIdentity } from "./AirlineIdentity";
import { FareBadge } from "./FareBadge";
import { FlightResultActions } from "./FlightResultActions";
import { ResultShareActions } from "./ResultShareActions";
import { SupplierSourceBadge } from "./SupplierSourceBadge";
import { TimeRouteBlock } from "./TimeRouteBlock";

type PairReturnCardProps = {
  option: PairedReturnOption;
  searchId?: string;
  searchParams?: URLSearchParams;
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
  const provider = (option.supplier_provider ?? option.provider ?? "").trim();
  return {
    offer_id: option.offer_id ?? option.combo_id,
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
    supplier_provider: provider || undefined,
    provider: provider || undefined,
    select_url: option.select_url,
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

export function PairReturnCard({
  option,
  searchId,
  searchParams,
  onSelect,
  onDetails,
  selecting,
}: PairReturnCardProps) {
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
  const shareOffer = useMemo(() => pairedOptionToOffer(option), [option]);

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
      <div
        className="mb-3 flex flex-col gap-2 rounded-jp-md border border-jp-border-soft bg-jp-page/40 px-2.5 py-2 sm:flex-row sm:items-center sm:gap-3 sm:px-3"
        data-testid="paired-journey-orientation-strip"
      >
        <div
          className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1"
          data-testid="paired-strip-departure"
        >
          <span
            className="inline-flex h-5 shrink-0 items-center rounded-jp-pill bg-jp-primary px-2 text-[10px] font-semibold tracking-wide text-white"
            data-testid="paired-strip-departure-badge"
          >
            Departure
          </span>
          <p className="min-w-0 text-xs leading-snug text-jp-text">
            <span className="font-semibold">
              {outbound.origin_airport_code} → {outbound.destination_airport_code}
            </span>
            <span className="text-jp-text-muted">
              {" · "}
              {outbound.departure_date_display || "—"}
              {outbound.duration_display ? ` · ${outbound.duration_display}` : ""}
              {" · "}
              {outbound.stops_label_display ||
                (outbound.stops === 0 ? "Direct" : `${outbound.stops} stop${outbound.stops === 1 ? "" : "s"}`)}
            </span>
          </p>
        </div>
        <div
          className="hidden h-6 w-px shrink-0 bg-jp-border-soft sm:block"
          aria-hidden="true"
          data-testid="paired-strip-divider"
        />
        <div
          className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1 border-t border-jp-border-soft pt-2 sm:border-t-0 sm:pt-0"
          data-testid="paired-strip-arrival"
        >
          <span
            className="inline-flex h-5 shrink-0 items-center rounded-jp-pill bg-jp-primary-soft px-2 text-[10px] font-semibold tracking-wide text-jp-primary"
            data-testid="paired-strip-arrival-badge"
          >
            Arrival
          </span>
          <p className="min-w-0 text-xs leading-snug text-jp-text">
            <span className="font-semibold">
              {inbound.origin_airport_code} → {inbound.destination_airport_code}
            </span>
            <span className="text-jp-text-muted">
              {" · "}
              {inbound.departure_date_display || "—"}
              {inbound.duration_display ? ` · ${inbound.duration_display}` : ""}
              {" · "}
              {inbound.stops_label_display ||
                (inbound.stops === 0 ? "Direct" : `${inbound.stops} stop${inbound.stops === 1 ? "" : "s"}`)}
            </span>
          </p>
        </div>
      </div>
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
              <span className="sr-only">Return airline</span>
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
            <div className="min-w-0" data-leg="outbound">
              <span className="sr-only">Outbound flight</span>
              <TimeRouteBlock
                departureTime={outbound.departure_time_display}
                arrivalTime={outbound.arrival_time_display}
                departureDate={outbound.departure_date_display}
                arrivalDate={outbound.arrival_date_display}
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
            <div className="min-w-0 border-t border-jp-border-soft pt-2 md:border-t-0 md:pt-0" data-leg="return">
              <span className="sr-only">Return flight</span>
              <TimeRouteBlock
                departureTime={inbound.departure_time_display}
                arrivalTime={inbound.arrival_time_display}
                departureDate={inbound.departure_date_display}
                arrivalDate={inbound.arrival_date_display}
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
          <div className="flex flex-col items-end gap-2">
            <ResultShareActions
              offer={shareOffer}
              searchParams={searchParams}
              displayAmount={selectedOption?.displayed_price ?? option.total_amount}
            />
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
      </div>
    </article>
  );
}
