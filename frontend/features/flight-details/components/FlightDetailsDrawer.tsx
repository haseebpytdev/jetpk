"use client";

import { useEffect, useRef } from "react";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";
import { SearchErrorState } from "@/features/flight-results/components/SearchErrorState";
import { useFlightDetails } from "../hooks/use-flight-details";
import { useRevalidation } from "../hooks/use-revalidation";
import type { FlightDetailsContext } from "../types";
import { ContinueToPassengersButton } from "./ContinueToPassengersButton";
import { FareChangeDialog } from "./FareChangeDialog";
import { FareFamilyDetails } from "./FareFamilyDetails";
import { FareSummaryTabs } from "./FareSummaryTabs";
import {
  OfferExpiredState,
  OfferUnavailableState,
  RevalidationPanel,
  SupplierTimeoutState,
} from "./OfferStatePanels";
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
  const scrollSurfaceRef = useRef<HTMLDivElement>(null);
  const details = useFlightDetails(open ? context : null);
  const revalidation = useRevalidation();

  useEffect(() => {
    if (!open) return;

    const root = document.documentElement;
    const body = document.body;
    const scrollX = window.scrollX;
    const scrollY = window.scrollY;
    const previousRootOverflow = root.style.overflow;
    const previousRootOverscrollBehavior = root.style.overscrollBehavior;
    const previousRootScrollBehavior = root.style.scrollBehavior;
    const previousBodyOverflow = body.style.overflow;
    const previousBodyOverscrollBehavior = body.style.overscrollBehavior;
    const previousBodyPaddingRight = body.style.paddingRight;
    const previousBodyPosition = body.style.position;
    const previousBodyTop = body.style.top;
    const previousBodyLeft = body.style.left;
    const previousBodyWidth = body.style.width;
    const scrollbarWidth = Math.max(0, window.innerWidth - root.clientWidth);
    const bodyPaddingRight = Number.parseFloat(window.getComputedStyle(body).paddingRight) || 0;

    root.style.overflow = "hidden";
    root.style.overscrollBehavior = "none";
    body.style.overflow = "hidden";
    body.style.overscrollBehavior = "none";
    body.style.position = "fixed";
    body.style.top = `-${scrollY}px`;
    body.style.left = `-${scrollX}px`;
    body.style.width = "100%";
    if (scrollbarWidth > 0) body.style.paddingRight = `${bodyPaddingRight + scrollbarWidth}px`;

    const preventBackgroundScroll = (event: WheelEvent | TouchEvent) => {
      if (event.target instanceof Node && scrollSurfaceRef.current?.contains(event.target)) return;
      event.preventDefault();
    };
    const restoreLockedScroll = () => {
      if (window.scrollX !== scrollX || window.scrollY !== scrollY) window.scrollTo(scrollX, scrollY);
    };
    document.addEventListener("wheel", preventBackgroundScroll, { passive: false });
    document.addEventListener("touchmove", preventBackgroundScroll, { passive: false });
    window.addEventListener("scroll", restoreLockedScroll, { passive: true });

    return () => {
      document.removeEventListener("wheel", preventBackgroundScroll);
      document.removeEventListener("touchmove", preventBackgroundScroll);
      window.removeEventListener("scroll", restoreLockedScroll);
      root.style.overflow = previousRootOverflow;
      root.style.overscrollBehavior = previousRootOverscrollBehavior;
      body.style.overflow = previousBodyOverflow;
      body.style.overscrollBehavior = previousBodyOverscrollBehavior;
      body.style.paddingRight = previousBodyPaddingRight;
      body.style.position = previousBodyPosition;
      body.style.top = previousBodyTop;
      body.style.left = previousBodyLeft;
      body.style.width = previousBodyWidth;

      root.style.scrollBehavior = "auto";
      window.scrollTo(scrollX, scrollY);
      root.style.scrollBehavior = previousRootScrollBehavior;
    };
  }, [open]);

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
  const isBookingIntent = context.intent === "booking";

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
      <div className="fixed inset-0 z-50 flex items-end justify-end overscroll-none sm:items-stretch" data-testid="flight-details-drawer">
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
          className="relative flex max-h-[94dvh] w-full flex-col rounded-t-2xl bg-jp-page shadow-jp-card sm:h-full sm:max-h-none sm:max-w-4xl sm:rounded-none lg:max-w-5xl"
        >
          <header className="flex items-start justify-between gap-4 border-b border-jp-border bg-jp-surface px-4 py-2.5 sm:px-5">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.14em] text-jp-primary">JetPakistan</p>
              <h2 id="flight-details-title" className="mt-0.5 text-lg font-semibold text-jp-text sm:text-xl">
                {isBookingIntent ? "Choose your flight & fare" : "Flight details"}
              </h2>
              <p className="mt-1 text-xs text-jp-text-muted">
                {isBookingIntent ? "Review the journey and confirm an available fare before continuing." : "Review the complete available journey and fare information."}
              </p>
            </div>
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

          <div
            ref={scrollSurfaceRef}
            className="min-h-0 flex-1 touch-pan-y overflow-y-auto overscroll-contain px-4 py-4 sm:px-5"
            data-testid="flight-details-scroll-surface"
          >
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
              <div className="space-y-4">
                {isInquiry ? (
                  <p className="text-sm text-jp-text-muted" role="note">
                    {details.data?.inquiry_only_notice ?? offer.inquiry_only_notice ?? "Multi-city inquiry only."}
                  </p>
                ) : null}

                <section className="rounded-jp-card border border-jp-border bg-jp-surface p-3.5" aria-labelledby="journey-details-heading">
                  <h3 id="journey-details-heading" className="mb-3 text-sm font-semibold text-jp-text">Journey details</h3>
                  <ReturnJourneyDetails returnCombo={details.data?.return_combo} />
                  <RouteTimeline segments={segments} layovers={offer.layovers_display} />
                  <div className="mt-3"><SegmentDetails segments={segments} /></div>
                </section>

                <FareFamilyDetails
                  options={details.fareOptions}
                  selectedKey={details.selectedFareKey}
                  onSelect={details.handleFareOptionChange}
                  disabled={revalidation.state === "loading"}
                />

                <FareSummaryTabs key={details.selectedFareKey || offer.offer_id} offer={offer} fallback={fallback} />

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
            <footer className="sticky bottom-0 border-t border-jp-border bg-jp-surface p-4 sm:px-6">
              <ContinueToPassengersButton
                loading={revalidation.state === "loading"}
                disabled={!canContinue}
                label={details.fareOptions.length > 1 ? "Continue with this fare" : "Continue with this flight"}
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
