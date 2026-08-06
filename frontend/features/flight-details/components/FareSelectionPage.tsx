"use client";

import { useMemo } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { OrderSummary } from "@/features/booking-layout";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";
import { SearchErrorState } from "@/features/flight-results/components/SearchErrorState";
import { BaggageDetails } from "@/features/flight-details/components/BaggageDetails";
import { ContinueToPassengersButton } from "@/features/flight-details/components/ContinueToPassengersButton";
import { MulticityInquiryActions } from "@/features/flight-results/components/MulticityInquiryActions";
import { FareChangeDialog } from "@/features/flight-details/components/FareChangeDialog";
import { FareFamilyDetails } from "@/features/flight-details/components/FareFamilyDetails";
import { FareRulesAccordion } from "@/features/flight-details/components/FareRulesAccordion";
import {
  OfferExpiredState,
  OfferUnavailableState,
  RevalidationPanel,
  SupplierTimeoutState,
} from "@/features/flight-details/components/OfferStatePanels";
import { PriceBreakdown } from "@/features/flight-details/components/PriceBreakdown";
import { ReturnJourneyDetails } from "@/features/flight-details/components/ReturnJourneyDetails";
import { RouteTimeline } from "@/features/flight-details/components/RouteTimeline";
import { SegmentDetails } from "@/features/flight-details/components/SegmentDetails";
import { useFlightDetails } from "@/features/flight-details/hooks/use-flight-details";
import { useRevalidation } from "@/features/flight-details/hooks/use-revalidation";
import type { FlightDetailsContext } from "@/features/flight-details/types";

export function FareSelectionPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const params = useMemo(() => new URLSearchParams(searchParams.toString()), [searchParams]);

  const context = useMemo<FlightDetailsContext | null>(() => {
    const searchId = params.get("search_id");
    const offerId = params.get("offer_id");
    if (!searchId || !offerId) return null;
    return {
      searchId,
      offerId,
      fareOptionKey: params.get("fare_option_key") ?? undefined,
      outboundKey: params.get("outbound_key") ?? undefined,
      comboId: params.get("combo_id") ?? undefined,
    };
  }, [params]);

  const details = useFlightDetails(context);
  const revalidation = useRevalidation();

  const offer = details.data?.offer;
  const fallback = offer?.fallback_details;
  const segments = offer?.segments ?? [];
  const isInquiry = details.data?.multicity_inquiry_only ?? offer?.multicity_inquiry_only;
  const canContinue = offer?.can_book && !isInquiry;

  const showRevalidationError =
    revalidation.state === "unavailable" ||
    revalidation.state === "expired" ||
    revalidation.state === "timeout" ||
    revalidation.state === "error";

  const handleContinue = () => {
    if (!offer || !context) return;
    void revalidation.continueToPassengers({
      searchId: context.searchId,
      offerId: offer.offer_id,
      fareOptionKey: details.selectedFareKey,
      selectUrl: offer.select_url,
      supplierProvider: offer.supplier_provider ?? offer.provider,
      isReturnCombo: Boolean(context.comboId),
      comboId: context.comboId,
      outboundKey: context.outboundKey,
    });
  };

  const progressSteps = [
    { key: "search", label: "Search", state: "completed" as const, href: "/" },
    { key: "results", label: "Results", state: "completed" as const, href: `/flights/results?search_id=${context?.searchId ?? ""}` },
    { key: "fare_selection", label: "Fare Selection", state: "current" as const },
    { key: "passenger_details", label: "Travelers", state: "upcoming" as const },
    { key: "review", label: "Review", state: "upcoming" as const },
    { key: "payment", label: "Payment", state: "upcoming" as const },
    { key: "confirmation", label: "Success", state: "upcoming" as const },
  ];

  if (!context) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-12">
        <SearchErrorState
          message="Missing search context. Please select a flight from results."
          onRetry={() => router.push("/flights/results")}
        />
      </div>
    );
  }

  return (
    <div className="jp-booking-shell mx-auto w-full max-w-jp-booking px-jp-xl py-jp-2xl" data-testid="fare-selection-page">
      <BookingProgress steps={progressSteps} />

      <header className="mb-jp-xl">
        <p className="text-jp-sm text-jp-muted">Home › Flights › Fare Selection</p>
        <h1 className="mt-jp-sm font-display text-jp-h2 font-bold text-jp-text">
          Choose Your <span className="text-jp-brand">Fare</span>
        </h1>
        <p className="mt-jp-xs text-jp-body text-jp-muted">
          Compare fare families and continue when you are ready. Prices are revalidated before checkout.
        </p>
      </header>

      <div className="grid gap-jp-xl lg:grid-cols-[minmax(0,1fr)_minmax(280px,330px)]">
        <div className="space-y-jp-lg">
          {details.loadState === "loading" ? <ResultSkeleton count={3} /> : null}
          {details.loadState === "error" ? (
            <SearchErrorState message={details.message ?? "Unable to load fare options."} onRetry={details.reload} />
          ) : null}
          {details.loadState === "expired" ? (
            <OfferExpiredState
              message={details.message ?? "This search has expired."}
              onNewSearch={() => router.push("/")}
            />
          ) : null}

          {details.loadState === "ready" && offer ? (
            <div className="space-y-jp-lg">
              {isInquiry ? (
                <MulticityInquiryActions
                  searchId={context.searchId}
                  offerId={offer.offer_id}
                  notice={details.data?.inquiry_only_notice ?? offer.inquiry_only_notice}
                  inquiryUrl={offer.inquiry_url}
                />
              ) : null}

              <ReturnJourneyDetails returnCombo={details.data?.return_combo} />
              <RouteTimeline segments={segments} layovers={offer.layovers_display} />
              <SegmentDetails segments={segments} />

              <FareFamilyDetails
                options={details.fareOptions}
                selectedKey={details.selectedFareKey}
                onSelect={details.handleFareOptionChange}
                disabled={revalidation.state === "loading"}
              />

              <BaggageDetails
                baggage={fallback?.baggage}
                summaryDisplay={offer.baggage_summary_display ?? offer.baggage}
                checkedDisplay={offer.baggage_checked_display}
                cabinDisplay={offer.baggage_cabin_display}
              />

              <FareRulesAccordion
                rules={fallback?.fare_rules}
                refundRule={offer.refund_rule}
                changeRule={offer.change_rule}
                refundable={offer.refundable}
              />

              <PriceBreakdown offer={offer} breakdown={fallback?.fare_breakdown} />

              {revalidation.state === "loading" ? (
                <RevalidationPanel message="Confirming fare with the airline…" />
              ) : null}
              {revalidation.state === "unavailable" ? (
                <OfferUnavailableState
                  title="Fare unavailable"
                  message={revalidation.message ?? "This fare is no longer available."}
                  onNewSearch={() => router.push("/")}
                />
              ) : null}
              {revalidation.state === "expired" ? (
                <OfferExpiredState
                  message={revalidation.message ?? "This search has expired."}
                  onNewSearch={() => router.push("/")}
                />
              ) : null}
              {revalidation.state === "timeout" ? (
                <SupplierTimeoutState
                  message={revalidation.message ?? "The airline took too long to respond."}
                  onRetry={handleContinue}
                />
              ) : null}
              {revalidation.state === "error" ? (
                <OfferUnavailableState
                  title="Could not continue"
                  message={revalidation.message ?? "Please try again."}
                />
              ) : null}
            </div>
          ) : null}

          {details.loadState === "ready" && offer && !showRevalidationError ? (
            <div className="flex flex-col gap-jp-sm sm:flex-row sm:items-center sm:justify-between">
              <button
                type="button"
                className="text-jp-sm font-semibold text-jp-brand hover:underline focus-visible:shadow-jp-focus"
                onClick={() => router.back()}
              >
                ← Back to Results
              </button>
              <ContinueToPassengersButton
                loading={revalidation.state === "loading"}
                disabled={!canContinue}
                onClick={handleContinue}
              />
            </div>
          ) : null}
        </div>

        <aside className="h-fit lg:sticky lg:top-[calc(var(--jp-nav-height)+1rem)]">
          {offer ? (
            <OrderSummary
              itinerary={{
                trip_type: details.data?.return_combo ? "round_trip" : "one_way",
                origin: segments[0]?.origin_airport_code ?? segments[0]?.origin ?? "",
                destination:
                  segments[segments.length - 1]?.destination_airport_code ??
                  segments[segments.length - 1]?.destination ??
                  "",
                depart_date: segments[0]?.departure_time_display ?? offer.departure_time ?? "",
                return_date: details.data?.return_combo?.return_journey
                  ? String(
                      (details.data.return_combo.return_journey as { departure_time_display?: string })
                        .departure_time_display ?? "",
                    ) || undefined
                  : undefined,
                airline_name: offer.airline_name,
                airline_code: offer.airline_code,
                flight_number: offer.flight_number,
                cabin: offer.cabin ?? "Economy",
                fare_family:
                  details.fareOptions.find((o) => o.option_key === details.selectedFareKey)?.brand_name ??
                  details.fareOptions.find((o) => o.option_key === details.selectedFareKey)?.name,
                segments: (offer.segments ?? []) as Array<Record<string, unknown>>,
                return_segments: [],
                currency: offer.currency ?? "PKR",
                total_formatted: offer.price_display,
              }}
            />
          ) : (
            <div className="rounded-jp-card border border-jp-border bg-jp-surface p-jp-lg shadow-jp-card">
              <h2 className="text-jp-sm font-semibold text-jp-text">Order Summary</h2>
              <p className="mt-jp-sm text-jp-sm text-jp-muted">Loading itinerary…</p>
            </div>
          )}
        </aside>
      </div>

      <FareChangeDialog
        open={revalidation.state === "fare_change"}
        originalTotal={revalidation.fareChange?.originalTotal}
        confirmedTotal={revalidation.fareChange?.confirmedTotal}
        currency={revalidation.fareChange?.currency}
        loading={revalidation.state === "loading"}
        onAccept={() => void revalidation.acceptFareChange()}
        onCancel={revalidation.reset}
      />
    </div>
  );
}
