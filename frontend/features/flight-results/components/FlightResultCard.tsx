"use client";

import { cn } from "@/lib/cn";
import { useMemo } from "react";
import type { FlightOffer } from "../types";
import { AirlineIdentity } from "./AirlineIdentity";
import { BaggageSummary } from "./BaggageSummary";
import { FareBadge } from "./FareBadge";
import { MulticityInquiryActions } from "./MulticityInquiryActions";
import { StopsAndLayover } from "./StopsAndLayover";
import { TimeRouteBlock } from "./TimeRouteBlock";

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
  const displayPrice = selectedOption?.price_display ?? offer.price_display;
  const viaCodes = extractViaCodes(offer);

  const firstSegment = offer.segments?.[0];
  const lastSegment = offer.segments?.[offer.segments.length - 1];

  return (
    <article
      className="rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card transition-shadow hover:shadow-md sm:p-4"
      data-testid="flight-result-card"
      aria-label={`${offer.airline_name ?? offer.airline_code ?? "Flight"} ${offer.departure_time ?? ""} to ${offer.arrival_time ?? ""}`}
    >
      <div className="grid items-center gap-3 sm:grid-cols-[minmax(8rem,0.8fr)_minmax(18rem,2.2fr)_minmax(9rem,1fr)] lg:gap-5">
        <div className="min-w-0">
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
            />
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <StopsAndLayover
              stops={offer.stops ?? 0}
              stopsLabel={offer.stops_label_display}
              layoverSummary={offer.layover_summary_display}
              viaCodes={viaCodes}
            />
            <FareBadge refundable={offer.refundable} seatsLeft={offer.seats_left} />
            <BaggageSummary offer={offer} />
          </div>
          {viaCodes.length > 0 ? <p className="text-xs text-jp-text-muted">Connection via {viaCodes.join(", ")}</p> : null}
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

        <div className="flex min-w-0 items-end justify-between gap-3 border-t border-jp-border-soft pt-3 sm:flex-col sm:items-end sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
          <div className="text-right">
            <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">{fareOptions.length > 1 ? "From" : "Total fare"}</p>
            <p className="text-lg font-bold text-jp-text" data-testid="result-price-display">
              {displayPrice ?? (displayAmount != null ? `PKR ${displayAmount.toLocaleString()}` : "Price unavailable")}
            </p>
          </div>
          <div className="flex gap-2">
          <button
            type="button"
            className="rounded-jp-md border border-jp-border px-3 py-2 text-sm font-medium text-jp-text hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            data-testid="flight-details-trigger"
            aria-label={`View details for ${offer.airline_name ?? "flight"}`}
            onClick={() => {
              const fareKeyForDetails = fareOptions.some((item) => item.option_key === selectedFareKey)
                ? selectedFareKey
                : undefined;
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
            onClick={() => onOpenDetails?.(offer, fareOptions[0]?.option_key ?? "", "booking")}
          >
            Book Now
          </button>
          </div>
        </div>
      </div>
    </article>
  );
}
