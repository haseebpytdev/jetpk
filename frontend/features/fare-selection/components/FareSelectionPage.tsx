"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import { BookingProgress } from "@/features/booking-progress";
import {
  BookingLayout,
  BookingLoadingState,
  BookingMainColumn,
  BookingPageHeader,
  BookingPageShell,
  BookingSection,
  BookingSectionHeader,
  BookingSidebar,
  OrderSummary,
} from "@/features/booking-layout";
import { preSessionFareSelectionProgress } from "@/features/booking-layout/constants/journey-steps";
import { BrandedFareCarousel } from "@/features/flight-results/components/BrandedFareCarousel";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchOfferDetails } from "@/features/flight-details/services/flight-details-api";
import { revalidateOffer } from "@/features/flight-results/services/flight-results-api";
import { resolvePassengerCheckoutHandoffUrl } from "@/features/flight-details/utils/handoff";
import { absoluteLaravelHandoffUrl, buildCheckoutHandoffUrl } from "@/features/flight-results/services/flight-results-api";
import type { FareFamilyOption, FlightOffer } from "@/features/flight-results/types";
import { parseFareSelectionParams } from "../utils/fare-selection-url";

type PageState = "loading" | "ready" | "expired" | "error";

function resolveFareOptions(offer: FlightOffer): FareFamilyOption[] {
  return offer.branded_fares_display_options?.length
    ? offer.branded_fares_display_options
    : offer.fare_family_options_display ?? [];
}

export function FareSelectionPage() {
  const router = useRouter();
  const rawParams = useSearchParams();
  const params = useMemo(() => {
    const record: Record<string, string | undefined> = {};
    rawParams.forEach((value, key) => {
      record[key] = value;
    });
    return parseFareSelectionParams(record);
  }, [rawParams]);

  const [pageState, setPageState] = useState<PageState>("loading");
  const [error, setError] = useState<string | null>(null);
  const [offer, setOffer] = useState<FlightOffer | null>(null);
  const [selectedFareKey, setSelectedFareKey] = useState<string>("");
  const [continuing, setContinuing] = useState(false);

  const loadOffer = useCallback(async () => {
    if (!params) {
      setPageState("expired");
      setError("This offer link is incomplete. Please search again.");
      return;
    }

    setPageState("loading");
    setError(null);

    const response = await fetchOfferDetails({
      searchId: params.searchId,
      offerId: params.offerId,
      fareOptionKey: params.fareOptionKey,
    });

    if (!response.ok || !response.data.success || !response.data.offer) {
      setPageState("expired");
      setError(!response.ok ? response.message : "This offer is no longer available.");
      return;
    }

    const loaded = response.data.offer;
    const fares = resolveFareOptions(loaded);
    const validKey =
      params.fareOptionKey && fares.some((f) => f.option_key === params.fareOptionKey)
        ? params.fareOptionKey
        : fares[0]?.option_key ?? loaded.offer_id;

    setOffer(loaded);
    setSelectedFareKey(validKey);
    setPageState("ready");
  }, [params]);

  useEffect(() => {
    void loadOffer();
  }, [loadOffer]);

  const handleContinue = async () => {
    if (!params || !offer || !selectedFareKey) return;
    setContinuing(true);
    setError(null);

    const result = await revalidateOffer({
      searchId: params.searchId,
      offerId: params.offerId,
      selectedFareOptionId: selectedFareKey,
    });

    if (!result.ok) {
      setContinuing(false);
      setPageState("expired");
      setError(result.message ?? "This offer expired. Please search again.");
      return;
    }

    const passengersUrl =
      result.data.passengers_url ??
      (offer.select_url
        ? buildCheckoutHandoffUrl(offer.select_url, offer.offer_id, selectedFareKey, params.searchId)
        : null);

    if (!passengersUrl) {
      setContinuing(false);
      setError("Unable to continue. Please try again.");
      return;
    }

    const resolved = resolvePassengerCheckoutHandoffUrl(passengersUrl) ?? absoluteLaravelHandoffUrl(passengersUrl);
    router.push(resolved);
  };

  if (pageState === "loading") {
    return <BookingLoadingState message="Loading fare options…" />;
  }

  if (pageState === "expired" || !params) {
    return (
      <BookingPageShell testId="fare-selection-page">
        <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-xl text-center" data-testid="fare-selection-expired">
          <h1 className="font-display text-jp-heading-md font-bold text-jp-text">Offer unavailable</h1>
          <p className="mt-2 text-jp-sm text-jp-muted">{error ?? "This offer has expired or is no longer valid."}</p>
          <PrimaryButton type="button" className="mt-6" onClick={() => router.push(`/flights/results?search_id=${params?.searchId ?? ""}`)}>
            Return to results
          </PrimaryButton>
        </div>
      </BookingPageShell>
    );
  }

  const fareOptions = offer ? resolveFareOptions(offer) : [];
  const selectedFare = fareOptions.find((f) => f.option_key === selectedFareKey);

  return (
    <BookingPageShell testId="fare-selection-page">
      <BookingProgress steps={preSessionFareSelectionProgress()} className="mb-6" />
      <BookingPageHeader
        title="Select your fare"
        description="Choose a fare family to continue to traveler details."
      />

      <BookingLayout
        main={
          <BookingMainColumn>
            <BookingSection>
              <BookingSectionHeader title="Your itinerary" />
              {offer ? (
                <div className="space-y-3 text-jp-sm">
                  <p className="font-semibold text-jp-text">
                    {offer.segments?.[0]?.origin_airport_code ?? "—"} → {offer.segments?.[0]?.destination_airport_code ?? "—"}
                  </p>
                  <p className="text-jp-muted">
                    {offer.airline_name} · {offer.departure_time} – {offer.arrival_time} · {offer.duration}
                  </p>
                </div>
              ) : null}
            </BookingSection>

            <BookingSection>
              <BookingSectionHeader title="Choose your fare" />
              {fareOptions.length > 0 ? (
                <BrandedFareCarousel
                  options={fareOptions}
                  selectedKey={selectedFareKey}
                  onSelect={setSelectedFareKey}
                  onBook={setSelectedFareKey}
                />
              ) : (
                <p className="text-jp-sm text-jp-muted">No fare families available for this offer.</p>
              )}
            </BookingSection>

            <div className="flex flex-wrap items-center justify-between gap-4">
              <Link href={`/flights/results?search_id=${params.searchId}`} className="text-jp-sm font-semibold text-jp-primary">
                ← Back to results
              </Link>
              <PrimaryButton type="button" disabled={continuing || !selectedFareKey} onClick={() => void handleContinue()}>
                {continuing ? "Continuing…" : "Continue to travelers →"}
              </PrimaryButton>
            </div>
            {error ? <p className="text-jp-sm text-jp-danger" role="alert">{error}</p> : null}
          </BookingMainColumn>
        }
        sidebar={
          <BookingSidebar>
            <OrderSummary
              itinerary={{
                trip_type: "one_way",
                origin: offer?.segments?.[0]?.origin_airport_code ?? "",
                destination: offer?.segments?.[0]?.destination_airport_code ?? "",
                depart_date: "",
                cabin: "economy",
                segments: [],
                return_segments: [],
                currency: "PKR",
                airline_name: offer?.airline_name,
                fare_family: selectedFare?.name,
                total_formatted: selectedFare?.price_display ?? offer?.price_display,
              }}
              pricing={{
                currency: "PKR",
                base_fare: selectedFare?.displayed_price ?? offer?.displayed_price ?? 0,
                taxes: 0,
                service_charges: 0,
                total: selectedFare?.displayed_price ?? offer?.displayed_price ?? 0,
                formatted_total: selectedFare?.price_display ?? offer?.price_display ?? "—",
              }}
            />
          </BookingSidebar>
        }
      />
    </BookingPageShell>
  );
}
