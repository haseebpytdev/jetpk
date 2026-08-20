"use client";

import { cn } from "@/lib/cn";
import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo } from "react";
import type { FlightOffer } from "../types";
import { AirlineIdentity } from "./AirlineIdentity";
import { FareBadge } from "./FareBadge";
import { MulticityInquiryActions } from "./MulticityInquiryActions";
import { TimeRouteBlock } from "./TimeRouteBlock";
import { formatWholePkr } from "../utils/price";

type FlightResultCardProps = {
  offer: FlightOffer;
  searchId: string;
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

function resolveFareOptions(offer: FlightOffer) {
  const branded = offer.branded_fares_display_options ?? offer.fare_family_options_display ?? [];
  if (branded.length > 0) return branded;
  if (offer.has_fare_choice_options || offer.has_branded_fares) {
    return offer.fare_family_options_display ?? [];
  }
  return [];
}

export function FlightResultCard({ offer, searchId, onOpenDetails }: FlightResultCardProps) {
  const fareOptions = useMemo(() => resolveFareOptions(offer), [offer]);
  const selectedFareKey = fareOptions[0]?.option_key ?? "";

  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey);
  const displayAmount = selectedOption?.displayed_price ?? offer.displayed_price;
  const displayPrice = formatWholePkr(displayAmount ?? offer.final_customer_price);
  const viaCodes = extractViaCodes(offer);

  const firstSegment = offer.segments?.[0];
  const lastSegment = offer.segments?.[offer.segments.length - 1];

  return (
    <article
      className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card transition-all hover:border-jp-primary/30 hover:shadow-md focus-within:border-jp-primary/40 sm:px-4"
      data-testid="flight-result-card"
      aria-label={`${offer.airline_name ?? offer.airline_code ?? "Flight"} ${offer.departure_time ?? ""} to ${offer.arrival_time ?? ""}`}
    >
      <div className="grid items-center gap-3 md:grid-cols-[minmax(8rem,0.85fr)_minmax(16rem,2fr)_minmax(10.5rem,0.95fr)] lg:gap-4 xl:grid-cols-[minmax(10.5rem,1fr)_minmax(20rem,2.35fr)_minmax(12.5rem,0.95fr)]">
        <div className="min-w-0 md:pr-1">
          <AirlineIdentity code={offer.airline_code} name={offer.airline_name} logoUrl={offer.airline_logo_url} size="md" />
          <p className="mt-1 truncate text-xs text-jp-text-muted">
            {offer.flight_number ?? "Flight number not supplied"}
          </p>
          {offer.operating_airline_name && offer.operating_airline_name !== offer.airline_name ? (
            <p className="mt-0.5 truncate text-[11px] text-jp-text-muted">Operated by {offer.operating_airline_name}</p>
          ) : null}
        </div>

        <div className="min-w-0 space-y-2">
            <TimeRouteBlock
              departureTime={firstSegment?.departure_time_display ?? offer.departure_time}
              arrivalTime={lastSegment?.arrival_time_display ?? offer.arrival_time}
              arrivalDayOffset={offer.arrival_day_offset_display}
              originCode={firstSegment?.origin_airport_code ?? firstSegment?.origin}
              destinationCode={lastSegment?.destination_airport_code ?? lastSegment?.destination}
              duration={offer.duration ?? offer.segments?.map((segment) => segment.duration_display).filter(Boolean).join(" + ")}
              stops={offer.stops ?? 0}
              stopsLabel={offer.stops_label_display}
              viaCodes={viaCodes}
              layoverSummary={offer.layover_summary_display}
            />
          <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <FareBadge refundable={offer.refundable} seatsLeft={offer.seats_left} />
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

        <div className="flex min-w-0 items-end justify-between gap-3 border-t border-jp-border-soft pt-3 md:h-full md:min-w-[10.5rem] md:flex-col md:items-end md:justify-center md:border-l md:border-t-0 md:pl-3 md:pt-0 xl:min-w-[12.5rem] xl:pl-4">
          <div className="text-right">
            <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">{fareOptions.length > 1 ? "From" : "Total fare"}</p>
            <p className="whitespace-nowrap text-lg font-bold text-jp-text" data-testid="result-price-display">
              {displayPrice ?? "Price unavailable"}
            </p>
          </div>
          <div className="flex flex-wrap justify-end gap-2">
          <button
            type="button"
            className="rounded-jp-md border border-jp-border px-3 py-2 text-sm font-medium text-jp-text hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            data-testid="flight-details-trigger"
            aria-label={`View details for ${offer.airline_name ?? "flight"}`}
            onClick={() => {
              const fareKeyForDetails = resolveAuthoritativeFareOptionKey(selectedFareKey, fareOptions);
              onOpenDetails?.(offer, fareKeyForDetails ?? "", "details");
            }}
          >
            Details
          </button>
          <button
            type="button"
            className="rounded-jp-md bg-jp-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            data-testid="book-now-trigger"
            disabled={!offer.can_book || offer.multicity_inquiry_only}
            onClick={() => {
              const fareKeyForDetails = resolveAuthoritativeFareOptionKey(fareOptions[0]?.option_key, fareOptions);
              onOpenDetails?.(offer, fareKeyForDetails ?? "", "booking");
            }}
          >
            Book Now
          </button>
          </div>
        </div>
      </div>
    </article>
  );
}
