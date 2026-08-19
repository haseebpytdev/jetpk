"use client";

import { useCallback, useRef, useState } from "react";
import { resolvePassengerCheckoutHandoffUrl } from "@/features/flight-details/utils/handoff";
import {
  buildCheckoutHandoffUrl,
  revalidateOffer,
} from "../services/flight-results-api";
import type { FlightOffer } from "../types";

function isIatiOffer(offer: FlightOffer): boolean {
  const provider = (offer.supplier_provider ?? offer.provider ?? "").toLowerCase();
  return provider === "iati";
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
          const next = resolved.startsWith("http") ? resolved : resolved.startsWith("/") ? resolved : `/${resolved}`;
          window.location.assign(next);
          return;
        }

        const checkoutUrl = buildCheckoutHandoffUrl(
          offer.select_url,
          offer.offer_id,
          fareOptionKey,
          searchId,
        );
        const resolvedCheckout = resolvePassengerCheckoutHandoffUrl(checkoutUrl) ?? checkoutUrl;
        const next = resolvedCheckout.startsWith("http")
          ? resolvedCheckout
          : resolvedCheckout.startsWith("/")
            ? resolvedCheckout
            : `/${resolvedCheckout}`;
        window.location.assign(next);
      } finally {
        inFlightRef.current = false;
        setSelectingId(null);
      }
    },
    [searchId],
  );

  return { selectingId, error, selectOffer, clearError: () => setError(null) };
}
