"use client";

import { useCallback, useRef, useState } from "react";
import { buildFareSelectionUrl } from "@/features/fare-selection";
import type { FlightOffer } from "../types";

export function useOfferSelection(searchId: string) {
  const [selectingId, setSelectingId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const inFlightRef = useRef(false);

  const selectOffer = useCallback(
    async (offer: FlightOffer, fareOptionKey: string) => {
      if (inFlightRef.current || !offer.offer_id) {
        return;
      }

      if (!offer.can_book) {
        setError(offer.disabled_reason ?? "This fare cannot be booked online.");
        return;
      }

      if (!searchId) {
        setError("Search session expired. Please search again.");
        return;
      }

      inFlightRef.current = true;
      setSelectingId(offer.offer_id);
      setError(null);

      try {
        const fareSelectionUrl = buildFareSelectionUrl({
          searchId,
          offerId: offer.offer_id,
          fareOptionKey: fareOptionKey || undefined,
        });
        window.location.assign(fareSelectionUrl);
      } finally {
        inFlightRef.current = false;
        setSelectingId(null);
      }
    },
    [searchId],
  );

  return { selectingId, error, selectOffer, clearError: () => setError(null) };
}
