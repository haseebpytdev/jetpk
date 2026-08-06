"use client";

import { cn } from "@/lib/cn";
import { useMemo, useState } from "react";
import type { FlightOffer } from "../types";
import { AirlineIdentity } from "./AirlineIdentity";
import { BaggageSummary } from "./BaggageSummary";
import { BrandedFareCarousel } from "./BrandedFareCarousel";
import { FareBadge } from "./FareBadge";
import { FlightSegmentSummary } from "./FlightSegmentSummary";
import { PriceBlock } from "./PriceBlock";
import { MulticityInquiryActions } from "./MulticityInquiryActions";
import { StopsAndLayover } from "./StopsAndLayover";
import { TimeRouteBlock } from "./TimeRouteBlock";

type FlightResultCardProps = {
  offer: FlightOffer;
  searchId: string;
  selecting?: boolean;
  onSelect: (offer: FlightOffer, fareOptionKey: string) => void;
  onOpenDetails?: (offer: FlightOffer, fareOptionKey: string) => void;
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

export function FlightResultCard({ offer, searchId, selecting, onSelect, onOpenDetails }: FlightResultCardProps) {
  const fareOptions = useMemo(() => resolveFareOptions(offer), [offer]);
  const hasBranded = fareOptions.length > 1 || (offer.has_branded_fares && fareOptions.length > 0);
  const [selectedFareKey, setSelectedFareKey] = useState(
    () => fareOptions[0]?.option_key ?? offer.offer_id,
  );
  const [bookingKey, setBookingKey] = useState<string | null>(null);

  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey);
  const displayAmount = selectedOption?.displayed_price ?? offer.displayed_price;
  const displayPrice = selectedOption?.price_display ?? offer.price_display;
  const viaCodes = extractViaCodes(offer);

  const handleBook = (fareOptionKey: string) => {
    setBookingKey(fareOptionKey);
    onSelect(offer, fareOptionKey);
  };

  const firstSegment = offer.segments?.[0];
  const lastSegment = offer.segments?.[offer.segments.length - 1];

  return (
    <article
      className="rounded-jp-card border border-jp-border bg-jp-surface p-4 shadow-jp-card sm:p-5"
      data-testid="flight-result-card"
      aria-label={`${offer.airline_name ?? offer.airline_code ?? "Flight"} ${offer.departure_time ?? ""} to ${offer.arrival_time ?? ""}`}
    >
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div className="min-w-0 flex-1 space-y-3">
          <AirlineIdentity
            code={offer.airline_code}
            name={offer.airline_name}
            logoUrl={offer.airline_logo_url}
            size="lg"
          />
          {offer.segments && offer.segments.length > 0 ? (
            <FlightSegmentSummary segments={offer.segments} />
          ) : (
            <TimeRouteBlock
              departureTime={offer.departure_time}
              arrivalTime={offer.arrival_time}
              arrivalDayOffset={offer.arrival_day_offset_display}
              originCode={firstSegment?.origin_airport_code ?? firstSegment?.origin}
              destinationCode={lastSegment?.destination_airport_code ?? lastSegment?.destination}
              duration={offer.duration}
            />
          )}
          <div className="flex flex-wrap items-center gap-3">
            <StopsAndLayover
              stops={offer.stops ?? 0}
              stopsLabel={offer.stops_label_display}
              layoverSummary={offer.layover_summary_display}
              viaCodes={viaCodes}
            />
            <FareBadge refundable={offer.refundable} seatsLeft={offer.seats_left} />
          </div>
          <BaggageSummary offer={offer} />
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

        <div className="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
          <button
            type="button"
            className="rounded-jp-md border border-jp-border px-3 py-2 text-sm font-medium text-jp-text hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            data-testid="flight-details-trigger"
            aria-label={`View details for ${offer.airline_name ?? "flight"}`}
            onClick={() => onOpenDetails?.(offer, selectedFareKey)}
          >
            Details
          </button>
          {!hasBranded ? (
            <PriceBlock
              amount={displayAmount}
              priceDisplay={displayPrice}
              disabled={!offer.can_book || offer.multicity_inquiry_only}
              loading={selecting}
              onSelect={() => handleBook(selectedFareKey)}
            />
          ) : null}
        </div>
      </div>

      {hasBranded ? (
        <BrandedFareCarousel
          options={fareOptions}
          selectedKey={selectedFareKey}
          onSelect={setSelectedFareKey}
          onBook={handleBook}
          bookingOptionKey={bookingKey}
          disabled={!offer.can_book}
        />
      ) : null}
    </article>
  );
}
