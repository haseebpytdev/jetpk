"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo, useState } from "react";
import type { FareFamilyOption, FlightOffer } from "../types";
import { AirlineIdentity } from "./AirlineIdentity";
import { FareBadge } from "./FareBadge";
import { FlightResultActions } from "./FlightResultActions";
import { MulticityInquiryActions } from "./MulticityInquiryActions";
import { ResultShareActions } from "./ResultShareActions";
import { SupplierSourceBadge } from "./SupplierSourceBadge";
import { TimeRouteBlock } from "./TimeRouteBlock";
import { formatWholePkr } from "../utils/price";

type FlightResultCardProps = {
  offer: FlightOffer;
  searchId: string;
  searchParams?: URLSearchParams;
  onOpenDetails?: (offer: FlightOffer, fareOptionKey: string, intent: "details" | "booking") => void;
};

function extractViaCodes(offer: FlightOffer): string[] {
  const segments = offer.segments ?? [];
  if (segments.length < 2) return [];
  const codes: string[] = [];
  const seen = new Set<string>();
  for (let index = 0; index < segments.length - 1; index += 1) {
    const code = (segments[index].destination_airport_code ?? segments[index].destination ?? "").trim().toUpperCase();
    if (code && !seen.has(code)) {
      seen.add(code);
      codes.push(code);
    }
  }
  return codes;
}

function resolveFareOptions(offer: FlightOffer): FareFamilyOption[] {
  const branded = offer.branded_fares_display_options ?? offer.fare_family_options_display ?? [];
  if (branded.length > 0) return branded;
  if (offer.has_fare_choice_options || offer.has_branded_fares) {
    return offer.fare_family_options_display ?? [];
  }
  return [];
}

function resolveLayoverSummary(offer: FlightOffer): string[] | undefined {
  const display = offer.layover_summary_display;
  if (Array.isArray(display) && display.length > 0) return display;
  const legacy = offer.layover_summary;
  if (Array.isArray(legacy) && legacy.length > 0) return legacy;
  return undefined;
}

export function FlightResultCard({ offer, searchId, searchParams, onOpenDetails }: FlightResultCardProps) {
  const fareOptions = useMemo(() => resolveFareOptions(offer), [offer]);
  const [selectedFareKey, setSelectedFareKey] = useState(() => fareOptions[0]?.option_key ?? "");
  const [bookingOptionKey, setBookingOptionKey] = useState<string | null>(null);

  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey) ?? fareOptions[0];
  const effectiveFareKey = selectedOption?.option_key ?? selectedFareKey;
  const displayAmount = selectedOption?.displayed_price ?? offer.displayed_price;
  const displayPrice = formatWholePkr(displayAmount ?? offer.final_customer_price);
  const viaCodes = extractViaCodes(offer);
  const layoverSummary = resolveLayoverSummary(offer);

  const firstSegment = offer.segments?.[0];
  const lastSegment = offer.segments?.[offer.segments.length - 1];

  const openWithSelectedFare = (intent: "details" | "booking") => {
    const fareKeyForDetails = resolveAuthoritativeFareOptionKey(effectiveFareKey, fareOptions) ?? "";
    setBookingOptionKey(intent === "booking" ? effectiveFareKey || null : null);
    onOpenDetails?.(offer, fareKeyForDetails, intent);
  };

  return (
    <article
      className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card transition-all hover:border-jp-primary/30 hover:shadow-md focus-within:border-jp-primary/40 sm:px-4"
      data-testid="flight-result-card"
      data-selected-fare-key={effectiveFareKey || undefined}
      aria-label={`${offer.airline_name ?? offer.airline_code ?? "Flight"} ${offer.departure_time ?? ""} to ${offer.arrival_time ?? ""}`}
    >
      <div className="grid items-stretch gap-3 lg:grid-cols-[minmax(8rem,0.85fr)_minmax(0,2fr)_minmax(10.5rem,0.95fr)] lg:items-center lg:gap-4 xl:grid-cols-[minmax(10.5rem,1fr)_minmax(20rem,2.35fr)_minmax(12.5rem,0.95fr)]">
        <div className="min-w-0 lg:pr-1">
          <AirlineIdentity code={offer.airline_code} name={offer.airline_name} logoUrl={offer.airline_logo_url} size="md" />
          {offer.operating_airline_name && offer.operating_airline_name !== offer.airline_name ? (
            <p className="mt-0.5 truncate text-[11px] text-jp-text-muted">Operated by {offer.operating_airline_name}</p>
          ) : null}
        </div>

        <div className="min-w-0 space-y-2">
          <TimeRouteBlock
            departureTime={firstSegment?.departure_time_display ?? offer.departure_time}
            arrivalTime={lastSegment?.arrival_time_display ?? offer.arrival_time}
            arrivalDayOffset={offer.arrival_day_offset_display ?? offer.arrival_day_offset}
            departureDate={firstSegment?.departure_date_display}
            arrivalDate={lastSegment?.arrival_date_display}
            originCode={firstSegment?.origin_airport_code ?? firstSegment?.origin ?? offer.departure_airport_code}
            destinationCode={lastSegment?.destination_airport_code ?? lastSegment?.destination ?? offer.arrival_airport_code}
            duration={offer.duration ?? offer.segments?.map((segment) => segment.duration_display).filter(Boolean).join(" + ")}
            stops={offer.stops ?? 0}
            stopsLabel={offer.stops_label_display ?? offer.stops_display}
            viaCodes={viaCodes}
            layoverSummary={layoverSummary}
            layovers={offer.layovers_display}
          />
          <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <FareBadge refundable={offer.refundable} seatsLeft={offer.seats_left} />
            <SupplierSourceBadge label={offer.supplier_source_label} />
          </div>
          {offer.multicity_inquiry_only ? (
            <MulticityInquiryActions
              searchId={searchId}
              offerId={offer.offer_id}
              notice={offer.inquiry_only_notice}
              inquiryUrl={offer.inquiry_url}
            />
          ) : null}
          {offer.disabled_reason && !offer.can_book ? (
            <p className="text-sm text-amber-700">{offer.disabled_reason}</p>
          ) : null}
        </div>

        <div className="flex min-w-0 flex-wrap items-end justify-between gap-3 border-t border-jp-border-soft pt-3 lg:h-full lg:min-w-[10.5rem] lg:flex-col lg:items-end lg:justify-center lg:border-l lg:border-t-0 lg:pl-3 lg:pt-0 xl:min-w-[12.5rem] xl:pl-4">
          <div className="min-w-0 text-left sm:text-right">
            <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">
              {fareOptions.length > 1 && !selectedOption ? "From" : "Total fare"}
            </p>
            <p className="text-lg font-bold leading-tight text-jp-text break-words" data-testid="result-price-display">
              {displayPrice ?? "Price unavailable"}
            </p>
          </div>
          <div className="flex flex-col items-end gap-2">
            <ResultShareActions
              offer={offer}
              searchParams={searchParams}
              displayAmount={displayAmount ?? offer.final_customer_price}
            />
            <FlightResultActions
              onDetails={() => openWithSelectedFare("details")}
              onBook={() => openWithSelectedFare("booking")}
              canBook={Boolean(offer.can_book) && !offer.multicity_inquiry_only}
              booking={bookingOptionKey !== null}
              detailsTestId="flight-details-trigger"
              bookTestId="book-now-trigger"
              detailsAriaLabel={`View details for ${offer.airline_name ?? "flight"}`}
            />
          </div>
        </div>
      </div>
    </article>
  );
}
