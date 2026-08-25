"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useMemo, useState } from "react";
import type { FareFamilyOption, FlightOffer } from "../types";
import { AirlineIdentity } from "./AirlineIdentity";
import { FareBadge } from "./FareBadge";
import { MulticityInquiryActions } from "./MulticityInquiryActions";
import { SupplierSourceBadge } from "./SupplierSourceBadge";
import { TimeRouteBlock } from "./TimeRouteBlock";
import { formatWholePkr } from "../utils/price";
import {
  buildFlightShareText,
  buildSafePublicResultsShareUrl,
  buildWhatsAppShareUrl,
  copyTextToClipboard,
} from "../utils/share-flight";

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

function CopyIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <rect x="9" y="9" width="11" height="11" rx="2" stroke="currentColor" strokeWidth="1.75" />
      <path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1" stroke="currentColor" strokeWidth="1.75" />
    </svg>
  );
}

function WhatsAppIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M12.04 2C6.58 2 2.15 6.4 2.15 11.82c0 1.96.52 3.87 1.5 5.55L2 22l4.8-1.57a10 10 0 0 0 5.24 1.44h.01c5.46 0 9.89-4.4 9.89-9.82C21.94 6.4 17.5 2 12.04 2Zm0 17.91h-.01a8.1 8.1 0 0 1-4.12-1.13l-.3-.18-2.85.93.96-2.77-.19-.29a8.03 8.03 0 0 1-1.24-4.3c0-4.44 3.65-8.05 8.14-8.05 2.18 0 4.22.84 5.76 2.37a8 8 0 0 1 2.38 5.7c0 4.44-3.65 8.05-8.13 8.05Zm4.46-5.98c-.24-.12-1.44-.71-1.66-.79-.22-.08-.39-.12-.55.12-.16.24-.63.79-.77.95-.14.16-.29.18-.53.06-.24-.12-1.02-.37-1.94-1.19-.72-.63-1.2-1.41-1.34-1.65-.14-.24-.01-.37.11-.49.11-.11.24-.29.36-.43.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.43-.06-.12-.55-1.32-.75-1.81-.2-.48-.4-.41-.55-.42h-.47c-.16 0-.43.06-.65.3-.22.24-.86.84-.86 2.04s.88 2.37 1 2.53c.12.16 1.73 2.64 4.2 3.7.59.25 1.05.4 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.44-.59 1.64-1.16.2-.57.2-1.06.14-1.16-.06-.1-.22-.16-.46-.28Z" />
    </svg>
  );
}

export function FlightResultCard({ offer, searchId, searchParams, onOpenDetails }: FlightResultCardProps) {
  const fareOptions = useMemo(() => resolveFareOptions(offer), [offer]);
  const [selectedFareKey, setSelectedFareKey] = useState(() => fareOptions[0]?.option_key ?? "");
  const [bookingOptionKey, setBookingOptionKey] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey) ?? fareOptions[0];
  const effectiveFareKey = selectedOption?.option_key ?? selectedFareKey;
  const displayAmount = selectedOption?.displayed_price ?? offer.displayed_price;
  const displayPrice = formatWholePkr(displayAmount ?? offer.final_customer_price);
  const viaCodes = extractViaCodes(offer);
  const layoverSummary = resolveLayoverSummary(offer);

  const firstSegment = offer.segments?.[0];
  const lastSegment = offer.segments?.[offer.segments.length - 1];

  const shareParams = useMemo(
    () => searchParams ?? (typeof window !== "undefined" ? new URLSearchParams(window.location.search) : new URLSearchParams()),
    [searchParams],
  );
  const shareUrl = useMemo(() => buildSafePublicResultsShareUrl(shareParams), [shareParams]);
  const shareText = useMemo(
    () => buildFlightShareText(offer, displayAmount ?? offer.final_customer_price, shareUrl),
    [offer, displayAmount, shareUrl],
  );
  const whatsappUrl = useMemo(() => buildWhatsAppShareUrl(shareText), [shareText]);

  const handleCopy = async () => {
    const ok = await copyTextToClipboard(shareText);
    if (!ok) return;
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  };

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
            arrivalDayOffset={offer.arrival_day_offset_display ?? offer.arrival_day_offset}
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

        <div className="flex min-w-0 items-end justify-between gap-3 border-t border-jp-border-soft pt-3 md:h-full md:min-w-[10.5rem] md:flex-col md:items-end md:justify-center md:border-l md:border-t-0 md:pl-3 md:pt-0 xl:min-w-[12.5rem] xl:pl-4">
          <div className="text-right">
            <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">
              {fareOptions.length > 1 && !selectedOption ? "From" : "Total fare"}
            </p>
            <p className="whitespace-nowrap text-lg font-bold text-jp-text" data-testid="result-price-display">
              {displayPrice ?? "Price unavailable"}
            </p>
          </div>
          <div className="flex flex-col items-end gap-2">
            <div className="flex items-center gap-1" data-testid="result-share-actions">
              <button
                type="button"
                className="inline-flex h-8 w-8 items-center justify-center rounded-jp-md border border-jp-border text-jp-text-muted hover:border-jp-primary hover:text-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
                aria-label={copied ? "Copied flight details" : "Copy flight details"}
                title={copied ? "Copied" : "Copy"}
                data-testid="result-copy-share"
                onClick={() => void handleCopy()}
              >
                <CopyIcon className="h-3.5 w-3.5" />
              </button>
              <a
                href={whatsappUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex h-8 w-8 items-center justify-center rounded-jp-md border border-jp-border text-jp-text-muted hover:border-jp-primary hover:text-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
                aria-label="Share on WhatsApp"
                title="WhatsApp"
                data-testid="result-whatsapp-share"
              >
                <WhatsAppIcon className="h-3.5 w-3.5" />
              </a>
              {copied ? (
                <span className="text-[10px] font-medium text-jp-primary" aria-live="polite">
                  Copied
                </span>
              ) : null}
            </div>
            <div className="flex flex-wrap justify-end gap-2">
              <button
                type="button"
                className="rounded-jp-md border border-jp-border px-3 py-2 text-sm font-medium text-jp-text hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
                data-testid="flight-details-trigger"
                aria-label={`View details for ${offer.airline_name ?? "flight"}`}
                onClick={() => openWithSelectedFare("details")}
              >
                Details
              </button>
              <button
                type="button"
                className="rounded-jp-md bg-jp-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                data-testid="book-now-trigger"
                disabled={!offer.can_book || offer.multicity_inquiry_only}
                onClick={() => openWithSelectedFare("booking")}
              >
                Book Now
              </button>
            </div>
          </div>
        </div>
      </div>
    </article>
  );
}
