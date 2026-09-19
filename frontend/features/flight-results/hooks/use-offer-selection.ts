"use client";

import { useCallback, useRef, useState } from "react";
import { resolvePassengerCheckoutHandoffUrl } from "@/features/flight-details/utils/handoff";
import { primePassengersContextBeforeHardNav } from "@/features/standard-booking/services/standard-booking-api";
import {
  buildCheckoutHandoffUrl,
  revalidateOffer,
} from "../services/flight-results-api";
import type { FlightOffer } from "../types";

function isIatiOffer(offer: FlightOffer): boolean {
  const provider = (offer.supplier_provider ?? offer.provider ?? "").toLowerCase();
  return provider === "iati";
}

function toAbsoluteHandoff(url: string): string {
  const resolved = url.startsWith("http") ? url : url.startsWith("/") ? url : `/${url}`;
  if (typeof window === "undefined") return resolved;
  return resolved.startsWith("http") ? resolved : `${window.location.origin}${resolved}`;
}

export function useOfferSelection(searchId: string) {
  const [selectingId, setSelectingId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const inFlightRef = useRef(false);

  const selectOffer = useCallback(
    async (offer: FlightOffer, fareOptionKey: string) => {
      if (inFlightRef.current || !offer.offer_id || !offer.select_url) {
        return;
      }

      if (!offer.can_book) {
        setError(offer.disabled_reason ?? "This fare cannot be booked online.");
        return;
      }

      inFlightRef.current = true;
      setSelectingId(offer.offer_id);
      setError(null);

      try {
        if (isIatiOffer(offer)) {
          const revalidation = await revalidateOffer({
            searchId,
            offerId: offer.offer_id,
            selectedFareOptionId: fareOptionKey || undefined,
          });

          if (!revalidation.ok) {
            setError(revalidation.message);
            return;
          }

          const passengersUrl = revalidation.data.passengers_url;
          if (!passengersUrl) {
            setError("Unable to continue to checkout. Please try again.");
            return;
          }

          const resolved = resolvePassengerCheckoutHandoffUrl(passengersUrl) ?? passengersUrl;
          const absolute = toAbsoluteHandoff(resolved);
          // Bounded pre-nav prime (same contract as use-revalidation). Failure/timeout
          // must not block handoff — Traveler falls back to early-document / React fetch.
          await primePassengersContextBeforeHardNav(absolute, { timeoutMs: 2500 });
          window.location.assign(absolute);
          return;
        }

        const checkoutUrl = buildCheckoutHandoffUrl(
          offer.select_url,
          offer.offer_id,
          fareOptionKey,
          searchId,
        );
        const resolvedCheckout = resolvePassengerCheckoutHandoffUrl(checkoutUrl) ?? checkoutUrl;
        const absolute = toAbsoluteHandoff(resolvedCheckout);
        await primePassengersContextBeforeHardNav(absolute, { timeoutMs: 2500 });
        window.location.assign(absolute);
      } finally {
        inFlightRef.current = false;
        setSelectingId(null);
      }
    },
    [searchId],
  );

  return { selectingId, error, selectOffer, clearError: () => setError(null) };
}
