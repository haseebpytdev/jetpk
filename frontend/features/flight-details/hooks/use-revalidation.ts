"use client";

import { useCallback, useRef, useState } from "react";
import {
  absoluteLaravelHandoffUrl,
  buildCheckoutHandoffUrl,
  revalidateOffer,
  submitReturnComboSelection,
} from "@/features/flight-results/services/flight-results-api";
import type { RevalidateOfferResponse } from "@/features/flight-results/types";
import type { RevalidationState } from "../types";
import { isAllowedInternalHandoffUrl, providerRequiresRevalidation, resolveHandoffUrl } from "../utils/handoff";

export type RevalidationParams = {
  searchId: string;
  offerId: string;
  fareOptionKey?: string;
  selectUrl?: string;
  supplierProvider?: string;
  isReturnCombo?: boolean;
  comboId?: string;
  outboundKey?: string;
};

function readTotal(value: unknown): number | undefined {
  return typeof value === "number" && Number.isFinite(value) ? value : undefined;
}

export function useRevalidation() {
  const [state, setState] = useState<RevalidationState>("idle");
  const [message, setMessage] = useState<string | null>(null);
  const [fareChange, setFareChange] = useState<{
    originalTotal?: number;
    confirmedTotal?: number;
    currency?: string;
    passengersUrl?: string;
  } | null>(null);
  const inFlightRef = useRef(false);
  const pendingHandoffRef = useRef<string | null>(null);
  const lastParamsRef = useRef<RevalidationParams | null>(null);

  const reset = useCallback(() => {
    setState("idle");
    setMessage(null);
    setFareChange(null);
    pendingHandoffRef.current = null;
    lastParamsRef.current = null;
    inFlightRef.current = false;
  }, []);

  const classifyFailure = useCallback((status: number, body?: RevalidateOfferResponse): RevalidationState => {
    const apiStatus = (body?.status ?? "").toLowerCase();
    if (status === 410 || apiStatus.includes("expired")) return "expired";
    if (apiStatus.includes("timeout") || apiStatus.includes("timed_out")) return "timeout";
    if (apiStatus.includes("unavailable") || apiStatus === "offer_not_found" || apiStatus === "offer_stale") {
      return "unavailable";
    }
    return "error";
  }, []);

  const extractFareChange = useCallback((body: RevalidateOfferResponse) => {
    const revalidation = body.revalidation ?? {};
    const priceChanged =
      body.status === "fare_changed" ||
      body.requires_fare_change_acceptance === true ||
      Boolean(revalidation.price_changed) ||
      revalidation.revalidation_status === "changed" ||
      Boolean(body.offer_freshness?.price_changed);

    if (!priceChanged) return null;

    const originalTotal =
      readTotal(revalidation.original_total) ??
      readTotal(revalidation.old_total) ??
      readTotal((revalidation as Record<string, unknown>).previous_total);
    const confirmedTotal =
      readTotal(revalidation.confirmed_total) ??
      readTotal(revalidation.new_total) ??
      readTotal((revalidation as Record<string, unknown>).updated_total);

    return {
      originalTotal,
      confirmedTotal,
      currency: typeof revalidation.currency === "string" ? revalidation.currency : "PKR",
      passengersUrl: body.passengers_url,
    };
  }, []);

  const navigateHandoff = useCallback((url: string) => {
    const resolved = resolveHandoffUrl(url) ?? (isAllowedInternalHandoffUrl(url) ? absoluteLaravelHandoffUrl(url) : null);
    if (!resolved) {
      setState("error");
      setMessage("Unable to continue to checkout. Please try again.");
      return false;
    }
    window.location.assign(resolved);
    return true;
  }, []);

  const runRevalidation = useCallback(
    async (params: RevalidationParams, acceptFareChange = false) => {
      return revalidateOffer({
        searchId: params.searchId,
        offerId: params.offerId,
        selectedFareOptionId: params.fareOptionKey,
        acceptFareChange,
      });
    },
    [],
  );

  const continueToPassengers = useCallback(
    async (params: RevalidationParams) => {
      if (inFlightRef.current) return;
      inFlightRef.current = true;
      setState("loading");
      setMessage(null);
      setFareChange(null);
      lastParamsRef.current = params;

      try {
        if (params.isReturnCombo && params.comboId && params.outboundKey) {
          await submitReturnComboSelection({
            searchId: params.searchId,
            comboId: params.comboId,
            outboundKey: params.outboundKey,
            fareOptionKey: params.fareOptionKey,
          });
          return;
        }

        const needsRevalidation = providerRequiresRevalidation(params.supplierProvider);

        if (needsRevalidation) {
          const result = await runRevalidation(params, false);

          if (!result.ok) {
            const failureState = classifyFailure(result.status, result.data);
            setState(failureState);
            setMessage(result.message);
            return;
          }

          const change = extractFareChange(result.data);
          if (change) {
            pendingHandoffRef.current =
              result.data.passengers_url ??
              (params.selectUrl
                ? buildCheckoutHandoffUrl(
                    params.selectUrl,
                    params.offerId,
                    params.fareOptionKey ?? params.offerId,
                    params.searchId,
                  )
                : null);
            setFareChange(change);
            setState("fare_change");
            return;
          }

          const passengersUrl = result.data.passengers_url;
          if (passengersUrl) {
            navigateHandoff(passengersUrl);
            setState("success");
            return;
          }
        }

        if (!params.selectUrl) {
          setState("error");
          setMessage("Unable to continue to checkout. Please try again.");
          return;
        }

        const checkoutUrl = buildCheckoutHandoffUrl(
          params.selectUrl,
          params.offerId,
          params.fareOptionKey ?? params.offerId,
          params.searchId,
        );
        navigateHandoff(checkoutUrl);
        setState("success");
      } finally {
        inFlightRef.current = false;
      }
    },
    [classifyFailure, extractFareChange, navigateHandoff, runRevalidation],
  );

  const acceptFareChange = useCallback(async () => {
    if (inFlightRef.current) return;
    const params = lastParamsRef.current;
    if (!params) {
      setState("error");
      setMessage("Unable to accept the updated fare. Please try again.");
      return;
    }

    inFlightRef.current = true;
    setState("loading");

    try {
      if (providerRequiresRevalidation(params.supplierProvider)) {
        const result = await runRevalidation(params, true);

        if (!result.ok) {
          const failureState = classifyFailure(result.status, result.data);
          setState(failureState);
          setMessage(result.message);
          return;
        }

        const secondChange = extractFareChange(result.data);
        if (secondChange) {
          pendingHandoffRef.current = result.data.passengers_url ?? pendingHandoffRef.current;
          setFareChange(secondChange);
          setState("fare_change");
          setMessage("The fare changed again. Please review the updated price.");
          return;
        }

        const passengersUrl = result.data.passengers_url ?? pendingHandoffRef.current;
        if (!passengersUrl || !navigateHandoff(passengersUrl)) {
          setState("error");
          setMessage("Unable to accept the updated fare. Please try again.");
          return;
        }
        setState("success");
        return;
      }

      const handoff = pendingHandoffRef.current ?? fareChange?.passengersUrl ?? null;
      if (!handoff || !navigateHandoff(handoff)) {
        setState("error");
        setMessage("Unable to accept the updated fare. Please try again.");
      } else {
        setState("success");
      }
    } finally {
      inFlightRef.current = false;
    }
  }, [classifyFailure, extractFareChange, fareChange?.passengersUrl, navigateHandoff, runRevalidation]);

  return {
    state,
    message,
    fareChange,
    continueToPassengers,
    acceptFareChange,
    reset,
  };
}
