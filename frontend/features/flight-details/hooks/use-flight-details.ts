"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { fetchOfferDetails } from "../services/flight-details-api";
import { resolveAuthoritativeFareOptionKey } from "../utils/fare-option-key";
import type { FareFamilyOption, FlightDetailsContext, FlightOfferDetailsResponse } from "../types";

export type DetailsLoadState = "idle" | "loading" | "ready" | "error" | "expired";

export function useFlightDetails(context: FlightDetailsContext | null) {
  const [loadState, setLoadState] = useState<DetailsLoadState>("idle");
  const [message, setMessage] = useState<string | null>(null);
  const [data, setData] = useState<FlightOfferDetailsResponse | null>(null);
  const [selectedFareKey, setSelectedFareKey] = useState<string>("");
  const requestIdRef = useRef(0);

  const fareOptions: FareFamilyOption[] =
    data?.offer.branded_fares_display_options ??
    data?.offer.fare_family_options_display ??
    context?.initialFareOptions ??
    [];
  const fareOptionsRef = useRef(fareOptions);
  fareOptionsRef.current = fareOptions;

  const loadDetails = useCallback(
    async (fareOptionKey?: string) => {
      if (!context) return;
      const requestId = ++requestIdRef.current;
      setLoadState("loading");
      setMessage(null);

      const knownFareOptions =
        fareOptionsRef.current.length > 0
          ? fareOptionsRef.current
          : (context.initialFareOptions ??
            context.initialOffer?.branded_fares_display_options ??
            context.initialOffer?.fare_family_options_display ??
            []);
      const requestedFareKey = fareOptionKey ?? context.fareOptionKey;
      const authoritativeFareKey = resolveAuthoritativeFareOptionKey(requestedFareKey, knownFareOptions);

      const response = await fetchOfferDetails({
        searchId: context.searchId,
        offerId: context.comboId ?? context.offerId,
        fareOptionKey: authoritativeFareKey,
        outboundKey: context.outboundKey,
        comboId: context.comboId,
      });

      if (requestId !== requestIdRef.current) return;

      if (!response.ok) {
        setLoadState(response.status === 410 ? "expired" : "error");
        setMessage(response.message);
        setData(null);
        return;
      }

      setData(response.data);
      setLoadState("ready");
      const opts =
        response.data.offer.branded_fares_display_options ??
        response.data.offer.fare_family_options_display ??
        [];
      if (opts.length > 0) {
        const key =
          resolveAuthoritativeFareOptionKey(fareOptionKey ?? context.fareOptionKey, opts) ??
          opts[0]?.option_key ??
          "";
        setSelectedFareKey(key);
      } else {
        setSelectedFareKey("");
      }
    },
    [context],
  );

  useEffect(() => {
    if (!context) {
      setLoadState("idle");
      setData(null);
      setMessage(null);
      return;
    }
    void loadDetails(context.fareOptionKey);
    return () => {
      requestIdRef.current += 1;
    };
  }, [context, loadDetails]);

  const handleFareOptionChange = useCallback(
    (key: string) => {
      setSelectedFareKey(key);
      void loadDetails(key);
    },
    [loadDetails],
  );

  return {
    loadState,
    message,
    data,
    fareOptions,
    selectedFareKey,
    handleFareOptionChange,
    reload: () => loadDetails(selectedFareKey),
  };
}
