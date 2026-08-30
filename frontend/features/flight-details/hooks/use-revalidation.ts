"use client";

import { useCallback, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { bookNowTimingSnapshot, markBookNowTiming } from "@/features/flight-results/utils/book-now-timing";
import {
  absoluteLaravelHandoffUrl,
  buildCheckoutHandoffUrl,
  revalidateOffer,
  submitReturnComboSelection,
} from "@/features/flight-results/services/flight-results-api";
import type { RevalidateOfferResponse } from "@/features/flight-results/types";
import type { RevalidationState } from "../types";
import { isAllowedInternalHandoffUrl, providerRequiresRevalidation, resolvePassengerCheckoutHandoffUrl } from "../utils/handoff";
import { redirectIfGuestBookingBlocked } from "@/features/standard-booking/services/commerce-gates-service";
import { markResultsLeftForCheckout } from "@/features/flight-results/utils/checkout-nav";

export type RevalidationParams = {
  searchId: string;
  offerId: string;
  fareOptionKey?: string;
  selectUrl?: string;
  supplierProvider?: string;
  isReturnCombo?: boolean;
  comboId?: string;
  outboundKey?: string;
  outboundFareOptionKey?: string;
  returnFareOptionKey?: string;
};

function readTotal(value: unknown): number | undefined {
  return typeof value === "number" && Number.isFinite(value) ? value : undefined;
}

function paramsCacheKey(params: RevalidationParams): string {
  return [
    params.searchId,
    params.offerId,
    params.fareOptionKey ?? "",
    params.comboId ?? "",
    params.outboundKey ?? "",
    params.returnFareOptionKey ?? "",
    params.outboundFareOptionKey ?? "",
  ].join("|");
}

export function useRevalidation() {
  const router = useRouter();
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
  const acceptCountRef = useRef(0);
  const warmPromiseRef = useRef<{
    key: string;
    promise: Promise<Awaited<ReturnType<typeof revalidateOffer>>>;
  } | null>(null);
  const MAX_FARE_CHANGE_ACCEPTS = 2;

  const reset = useCallback(() => {
    setState("idle");
    setMessage(null);
    setFareChange(null);
    pendingHandoffRef.current = null;
    lastParamsRef.current = null;
    inFlightRef.current = false;
    acceptCountRef.current = 0;
    warmPromiseRef.current = null;
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

  const navigateHandoff = useCallback(async (url: string, fareOptionKey?: string, searchId?: string) => {
    let handoffUrl = url;
    const key = (fareOptionKey ?? "").trim();
    if (key) {
      try {
        const parsed = new URL(url, typeof window !== "undefined" ? window.location.origin : "https://jetpakistan.pk");
        if (!parsed.searchParams.get("fare_option_key")) {
          parsed.searchParams.set("fare_option_key", key);
        }
        handoffUrl = `${parsed.pathname}${parsed.search}`;
      } catch {
        if (!/[?&]fare_option_key=/.test(url)) {
          handoffUrl = `${url}${url.includes("?") ? "&" : "?"}fare_option_key=${encodeURIComponent(key)}`;
        }
      }
    }

    const resolved =
      resolvePassengerCheckoutHandoffUrl(handoffUrl) ??
      (isAllowedInternalHandoffUrl(handoffUrl) ? absoluteLaravelHandoffUrl(handoffUrl) : null);
    if (!resolved) {
      setState("error");
      setMessage("Unable to continue to checkout. Please try again.");
      return false;
    }
    if (await redirectIfGuestBookingBlocked(resolved)) {
      return false;
    }
    markResultsLeftForCheckout(searchId ?? lastParamsRef.current?.searchId);
    setState("loading");
    setMessage("Preparing your trip…");
    markBookNowTiming("T6_nav_start", { handoff: resolved.slice(0, 120) });

    const persistTimingForContinuity = () => {
      try {
        const snap = bookNowTimingSnapshot();
        if (snap && typeof sessionStorage !== "undefined") {
          sessionStorage.setItem("jp-book-now-timing", JSON.stringify(snap));
        }
      } catch {
        /* ignore */
      }
    };

    // R6F: hard location.assign made usable p50 ~21s (worse than R5A soft-nav ~3.7s).
    // Soft-nav is primary for /booking/passengers; keep continuous timing via
    // sessionStorage so T0→T9 survives remount. Hard assign only as fallback.
    const isPassengersHandoff =
      resolved.startsWith("/booking/passengers") ||
      /(?:^|\/)booking\/passengers(?:\?|$)/.test(resolved);
    if (isPassengersHandoff) {
      try {
        void import("@/features/standard-booking/components/PassengerDetailsPage");
        try {
          router.prefetch(resolved);
        } catch {
          /* non-blocking */
        }
        persistTimingForContinuity();
        markBookNowTiming("T7_passenger_route", { nav: "soft_push" });
        // Re-persist after T7 so restore sees T7_from_T0 if the route remounts.
        persistTimingForContinuity();
        router.push(resolved);
        return true;
      } catch {
        /* fall through to hard assign */
      }
      persistTimingForContinuity();
      markBookNowTiming("T7_passenger_route", { nav: "hard_assign_fallback" });
      persistTimingForContinuity();
      const target = resolved.startsWith("http")
        ? resolved
        : resolved.startsWith("/")
          ? resolved
          : `/${resolved}`;
      window.location.assign(target);
      return true;
    }
    window.location.assign(resolved);
    return true;
  }, [router]);

  const runRevalidation = useCallback(
    async (params: RevalidationParams, acceptFareChange = false) => {
      const key = `${paramsCacheKey(params)}|accept=${acceptFareChange ? 1 : 0}`;
      if (!acceptFareChange && warmPromiseRef.current?.key === key) {
        return warmPromiseRef.current.promise;
      }
      const promise = revalidateOffer({
        searchId: params.searchId,
        offerId: params.offerId,
        selectedFareOptionId: params.fareOptionKey,
        acceptFareChange,
      });
      markBookNowTiming("T2_revalidate_start", {
        offerId: params.offerId,
        provider: params.supplierProvider,
        acceptFareChange,
      });
      void promise.then(() => {
        markBookNowTiming("T3_revalidate_response");
      });
      if (!acceptFareChange) {
        warmPromiseRef.current = { key, promise };
      }
      return promise;
    },
    [],
  );

  /**
   * Start read-only fare revalidation while the traveler reviews the drawer.
   * Continue reuses the same in-flight/cached promise when fare keys match.
   */
  const warmStartRevalidation = useCallback(
    (params: RevalidationParams) => {
      if (!providerRequiresRevalidation(params.supplierProvider)) return;
      if (params.isReturnCombo) return;
      const key = `${paramsCacheKey(params)}|accept=0`;
      if (warmPromiseRef.current?.key === key) return;
      lastParamsRef.current = params;
      markBookNowTiming("T2_revalidate_start", {
        offerId: params.offerId,
        provider: params.supplierProvider,
        phase: "warm",
      });
      const promise = revalidateOffer({
        searchId: params.searchId,
        offerId: params.offerId,
        selectedFareOptionId: params.fareOptionKey,
        acceptFareChange: false,
      });
      warmPromiseRef.current = { key, promise };
      void promise.then((result) => {
        markBookNowTiming("T3_revalidate_response", { phase: "warm", ok: result.ok });
        if (warmPromiseRef.current?.key !== key) return;
        if (!result.ok) return;
        const change = extractFareChange(result.data);
        if (change) {
          pendingHandoffRef.current = result.data.passengers_url ?? null;
        }
      });
      return;
    },
    [extractFareChange],
  );

  const continueToPassengers = useCallback(
    async (params: RevalidationParams) => {
      if (inFlightRef.current) return;
      inFlightRef.current = true;
      setState("loading");
      setMessage("Preparing your trip…");
      markBookNowTiming("T1_handler", { phase: "continueToPassengers" });
      setFareChange(null);
      lastParamsRef.current = params;

      try {
        // Return combos still need a bounded read-only reprice of the selected outbound
        // offer before checkout handoff when the provider requires it.
        if (params.isReturnCombo && params.comboId && params.outboundKey) {
          if (providerRequiresRevalidation(params.supplierProvider)) {
            const result = await runRevalidation(params, false);
            if (!result.ok) {
              const failureState = classifyFailure(result.status, result.data);
              setState(failureState);
              setMessage(result.message);
              return;
            }
            const change = extractFareChange(result.data);
            if (change) {
              pendingHandoffRef.current = result.data.passengers_url ?? null;
              setFareChange(change);
              setState("fare_change");
              return;
            }
          }
          markResultsLeftForCheckout(params.searchId);
          markBookNowTiming("T4_draft_prep_start", { phase: "return_combo" });
          await submitReturnComboSelection({
            searchId: params.searchId,
            comboId: params.comboId,
            outboundKey: params.outboundKey,
            fareOptionKey: params.fareOptionKey,
            returnFareOptionKey: params.returnFareOptionKey ?? params.fareOptionKey,
            outboundFareOptionKey: params.outboundFareOptionKey,
          });
          markBookNowTiming("T5_draft_prep_done", { phase: "return_combo" });
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
            const ok = await navigateHandoff(
              passengersUrl,
              params.fareOptionKey || result.data.selected_fare_option_id || undefined,
              params.searchId,
            );
            if (!ok) {
              return;
            }
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
        const ok = await navigateHandoff(checkoutUrl, params.fareOptionKey, params.searchId);
        if (!ok) {
          return;
        }
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

    if (acceptCountRef.current >= MAX_FARE_CHANGE_ACCEPTS) {
      setState("error");
      setMessage("The fare kept changing. Please search again and select a new offer.");
      return;
    }
    acceptCountRef.current += 1;

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
          if (acceptCountRef.current >= MAX_FARE_CHANGE_ACCEPTS) {
            setState("error");
            setMessage("The fare kept changing. Please search again and select a new offer.");
            return;
          }
          pendingHandoffRef.current = result.data.passengers_url ?? pendingHandoffRef.current;
          setFareChange(secondChange);
          setState("fare_change");
          setMessage("The fare changed again. Please review the updated price.");
          return;
        }

        if (params.isReturnCombo && params.comboId && params.outboundKey) {
          markResultsLeftForCheckout(params.searchId);
          await submitReturnComboSelection({
            searchId: params.searchId,
            comboId: params.comboId,
            outboundKey: params.outboundKey,
            fareOptionKey: params.fareOptionKey,
            returnFareOptionKey: params.returnFareOptionKey ?? params.fareOptionKey,
            outboundFareOptionKey: params.outboundFareOptionKey,
          });
          setState("success");
          return;
        }

        const passengersUrl = result.data.passengers_url ?? pendingHandoffRef.current;
        if (!passengersUrl || !(await navigateHandoff(passengersUrl, params.fareOptionKey, params.searchId))) {
          setState("error");
          setMessage("Unable to accept the updated fare. Please try again.");
          return;
        }
        setState("success");
        return;
      }

      const handoff = pendingHandoffRef.current ?? fareChange?.passengersUrl ?? null;
      if (!handoff || !(await navigateHandoff(handoff, params.fareOptionKey, params.searchId))) {
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
    warmStartRevalidation,
    reset,
  };
}
