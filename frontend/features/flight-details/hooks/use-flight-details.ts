"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { fetchOfferDetails } from "../services/flight-details-api";
import { ensureSelectableFareCatalog, isBaseOfferFareOption } from "../utils/base-offer-fare";
import { resolveAuthoritativeFareOptionKey } from "../utils/fare-option-key";
import type { FareFamilyOption, FlightDetailsContext, FlightOfferDetailsResponse } from "../types";

export type DetailsLoadState = "idle" | "loading" | "ready" | "error" | "expired";

export function useFlightDetails(context: FlightDetailsContext | null) {
  const [loadState, setLoadState] = useState<DetailsLoadState>("idle");
  const [message, setMessage] = useState<string | null>(null);
  const [data, setData] = useState<FlightOfferDetailsResponse | null>(null);
  const [selectedFareKey, setSelectedFareKey] = useState<string>("");
  const requestIdRef = useRef(0);

  const rawFareOptions: FareFamilyOption[] =
    data?.offer.branded_fares_display_options ??
    data?.offer.fare_family_options_display ??
    context?.initialFareOptions ??
    [];
  const fareOptions: FareFamilyOption[] = ensureSelectableFareCatalog(rawFareOptions, data?.offer ?? context?.initialOffer);
  const fareOptionsRef = useRef(fareOptions);
  fareOptionsRef.current = fareOptions;

  const seedFromInitialOffer = useCallback(
    (fareOptionKey?: string) => {
      if (!context?.initialOffer) return false;
      const knownFareOptions =
        context.initialFareOptions ??
        context.initialOffer.branded_fares_display_options ??
        context.initialOffer.fare_family_options_display ??
        [];
      const seededOpts = ensureSelectableFareCatalog(knownFareOptions, context.initialOffer);
      // Never fall back to a non-authoritative/synthetic option_key for API/selection identity.
      const authoritativeFareKey =
        resolveAuthoritativeFareOptionKey(fareOptionKey ?? context.fareOptionKey, seededOpts) ?? "";
      const uiSelectedKey =
        authoritativeFareKey ||
        seededOpts.find((o) => isBaseOfferFareOption(o))?.option_key ||
        seededOpts[0]?.option_key ||
        "";
      const seeded: FlightOfferDetailsResponse = {
        success: true,
        search_id: context.searchId,
        offer_id: context.offerId,
        offer: context.initialOffer,
        fare_option_key: authoritativeFareKey || null,
      };
      setData(seeded);
      setLoadState("ready");
      setSelectedFareKey(uiSelectedKey);
      setMessage(null);
      return true;
    },
    [context],
  );

  const loadDetails = useCallback(
    async (fareOptionKey?: string, options?: { background?: boolean }) => {
      if (!context) return;
      const requestId = ++requestIdRef.current;
      const background = Boolean(options?.background);
      if (!background) {
        setLoadState("loading");
        setMessage(null);
      }

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
        if (background) {
          // Keep seeded card offer; do not blank the Continue CTA.
          return;
        }
        if (context.initialOffer && (context.legMode === "outbound_confirm" || context.legMode === "pair" || context.legMode === "return_confirm" || context.intent === "booking")) {
          seedFromInitialOffer(fareOptionKey);
          return;
        }
        setLoadState(response.status === 410 ? "expired" : "error");
        setMessage(response.message);
        setData(null);
        return;
      }

      setData(response.data);
      setLoadState("ready");
      const opts = ensureSelectableFareCatalog(
        response.data.offer.branded_fares_display_options
          ?? response.data.offer.fare_family_options_display
          ?? [],
        response.data.offer,
      );
      if (opts.length > 0) {
        const preferred = fareOptionKey ?? context.fareOptionKey;
        const key =
          resolveAuthoritativeFareOptionKey(preferred, opts)
          ?? (opts.some((o) => o.option_key === preferred) ? preferred : undefined)
          ?? opts[0]?.option_key
          ?? "";
        setSelectedFareKey(key ?? "");
      } else {
        setSelectedFareKey("");
      }
    },
    [context, seedFromInitialOffer],
  );

  useEffect(() => {
    if (!context) {
      setLoadState("idle");
      setData(null);
      setMessage(null);
      return;
    }
    // Card already has the offer — paint Continue immediately; enrich in background.
    // Dominant Book Now delay was waiting on fetchOfferDetails before the CTA appeared.
    if (context.initialOffer && (context.intent === "booking" || context.legMode === "pair" || context.legMode === "outbound_confirm" || context.legMode === "return_confirm")) {
      seedFromInitialOffer(context.fareOptionKey);
      void loadDetails(context.fareOptionKey, { background: true });
    } else {
      void loadDetails(context.fareOptionKey);
    }
    return () => {
      requestIdRef.current += 1;
    };
  }, [context, loadDetails, seedFromInitialOffer]);

  const handleFareOptionChange = useCallback(
    async (key: string) => {
      const option = fareOptionsRef.current.find((item) => item.option_key === key);
      if (isBaseOfferFareOption(option)) {
        setSelectedFareKey(key);
        return;
      }
      if (!resolveAuthoritativeFareOptionKey(key, fareOptionsRef.current)) return;
      setSelectedFareKey(key);
      await loadDetails(key);
    },
    [loadDetails],
  );

  const handleViewDetails = useCallback(
    async (key: string) => {
      if (!resolveAuthoritativeFareOptionKey(key, fareOptionsRef.current)) {
        document.querySelector('[data-testid="fare-summary-tabs"]')?.scrollIntoView({ behavior: "smooth", block: "start" });
        return;
      }
      setSelectedFareKey(key);
      await loadDetails(key);
      document.querySelector('[data-testid="fare-summary-tabs"]')?.scrollIntoView({ behavior: "smooth", block: "start" });
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
    handleViewDetails,
    reload: () => loadDetails(selectedFareKey),
  };
}
