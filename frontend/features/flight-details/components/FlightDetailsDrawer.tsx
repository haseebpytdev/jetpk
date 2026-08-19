"use client";

import { useEffect, useRef } from "react";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";
import { SearchErrorState } from "@/features/flight-results/components/SearchErrorState";
import { useFlightDetails } from "../hooks/use-flight-details";
import { useRevalidation } from "../hooks/use-revalidation";
import type { FlightDetailsContext } from "../types";
import { BaggageDetails } from "./BaggageDetails";
import { ContinueToPassengersButton } from "./ContinueToPassengersButton";
import { FareChangeDialog } from "./FareChangeDialog";
import { FareFamilyDetails } from "./FareFamilyDetails";
import { FareRulesAccordion } from "./FareRulesAccordion";
import {
  OfferExpiredState,
  OfferUnavailableState,
  RevalidationPanel,
  SupplierTimeoutState,
} from "./OfferStatePanels";
import { PriceBreakdown } from "./PriceBreakdown";
import { ReturnJourneyDetails } from "./ReturnJourneyDetails";
import { RouteTimeline } from "./RouteTimeline";
import { SegmentDetails } from "./SegmentDetails";

type FlightDetailsDrawerProps = {
  open: boolean;
  context: FlightDetailsContext | null;
  onClose: () => void;
  triggerRef?: React.RefObject<HTMLElement | null>;
  onNewSearch?: () => void;
};

export function FlightDetailsDrawer({
  open,
  context,
  onClose,
  triggerRef,
  onNewSearch,
}: FlightDetailsDrawerProps) {
  const closeRef = useRef<HTMLButtonElement>(null);
  const details = useFlightDetails(open ? context : null);
  const revalidation = useRevalidation();

  useEffect(() => {
    if (!open) {
      revalidation.reset();
      return;
    }
    closeRef.current?.focus();
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        onClose();
        triggerRef?.current?.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset is stable; avoid revalidation object identity churn
  }, [onClose, open, triggerRef]);

  if (!open || !context) return null;

  const offer = details.data?.offer;
  const fallback = offer?.fallback_details;
  const segments = offer?.segments ?? [];
  const isInquiry = details.data?.multicity_inquiry_only ?? offer?.multicity_inquiry_only;
  const canContinue = offer?.can_book && !isInquiry;

  const handleContinue = () => {
    if (!offer) return;
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

  const showRevalidationError =
    revalidation.state === "unavailable" ||
    revalidation.state === "expired" ||
    revalidation.state === "timeout" ||
    revalidation.state === "error";

  return (
    <>
      <div className="fixed inset-0 z-50 flex justify-end" data-testid="flight-details-drawer">
        <button
          type="button"
          className="absolute inset-0 bg-black/40"
          aria-label="Close flight details"
          onClick={() => {
            onClose();
            triggerRef?.current?.focus();
          }}
        />
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="flight-details-title"
          className="relative flex h-full w-full max-w-lg flex-col bg-jp-page shadow-jp-card sm:max-w-xl"
        >
          <header className="flex items-center justify-between border-b border-jp-border px-4 py-3">
            <h2 id="flight-details-title" className="text-lg font-semibold text-jp-text">
              Flight details
            </h2>
            <button
              ref={closeRef}
              type="button"
              className="rounded-jp-md border border-jp-border px-3 py-1 text-sm font-medium text-jp-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
              onClick={() => {
                onClose();
                triggerRef?.current?.focus();
              }}
            >
              Close
            </button>
          </header>

          <div className="flex-1 overflow-y-auto px-4 py-4">
            {details.loadState === "loading" ? <ResultSkeleton count={2} /> : null}
            {details.loadState === "error" ? (
              <SearchErrorState message={details.message ?? "Unable to load details."} onRetry={details.reload} />
            ) : null}
            {details.loadState === "expired" ? (
              <OfferExpiredState
                message={details.message ?? "This search has expired."}
                onClose={onClose}
                onNewSearch={onNewSearch}
              />
            ) : null}

            {details.loadState === "ready" && offer ? (
              <div className="space-y-6">
                {isInquiry ? (
                  <p className="text-sm text-jp-text-muted" role="note">
                    {details.data?.inquiry_only_notice ?? offer.inquiry_only_notice ?? "Multi-city inquiry only."}
                  </p>
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

                <section data-testid="fare-details-baggage">
                  <h3 className="text-sm font-semibold text-jp-text">Baggage Policy</h3>
                  <BaggageDetails
                    baggage={fallback?.baggage}
                    summaryDisplay={offer.baggage_summary_display ?? offer.baggage}
                    checkedDisplay={offer.baggage_checked_display}
                    cabinDisplay={offer.baggage_cabin_display}
                  />
                </section>

                <section data-testid="fare-details-policy">
                  <h3 className="text-sm font-semibold text-jp-text">Fare Policy</h3>
                  <FareRulesAccordion
                    rules={fallback?.fare_rules}
                    refundRule={offer.refund_rule}
                    changeRule={offer.change_rule}
                    refundable={offer.refundable}
                  />
                </section>

                <section data-testid="fare-details-breakdown">
                  <h3 className="text-sm font-semibold text-jp-text">Fare Details</h3>
                  <PriceBreakdown offer={offer} breakdown={fallback?.fare_breakdown} />
                </section>

                {revalidation.state === "loading" ? (
                  <RevalidationPanel message="Confirming fare with the airline…" />
                ) : null}

                {revalidation.state === "unavailable" ? (
                  <OfferUnavailableState
                    title="Fare unavailable"
                    message={revalidation.message ?? "This fare is no longer available."}
                    onClose={onClose}
                    onNewSearch={onNewSearch}
                  />
                ) : null}
                {revalidation.state === "expired" ? (
                  <OfferExpiredState
                    message={revalidation.message ?? "This search has expired."}
                    onClose={onClose}
                    onNewSearch={onNewSearch}
                  />
                ) : null}
                {revalidation.state === "timeout" ? (
                  <SupplierTimeoutState
                    message={revalidation.message ?? "The airline took too long to respond."}
                    onRetry={handleContinue}
                    onClose={onClose}
                  />
                ) : null}
                {revalidation.state === "error" ? (
                  <OfferUnavailableState
                    title="Could not continue"
                    message={revalidation.message ?? "Please try again."}
                    onClose={onClose}
                  />
                ) : null}
              </div>
            ) : null}
          </div>

          {details.loadState === "ready" && offer && !showRevalidationError ? (
            <footer className="sticky bottom-0 border-t border-jp-border bg-jp-page p-4">
              <ContinueToPassengersButton
                loading={revalidation.state === "loading"}
                disabled={!canContinue}
                onClick={handleContinue}
              />
              {!canContinue && offer.disabled_reason ? (
                <p className="mt-2 text-xs text-jp-text-muted">{offer.disabled_reason}</p>
              ) : null}
            </footer>
          ) : null}
        </div>
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
    </>
  );
}
