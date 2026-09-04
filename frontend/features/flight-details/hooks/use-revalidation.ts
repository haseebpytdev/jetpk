"use client";

import { useCallback, useRef, useState } from "react";
import { flushSync } from "react-dom";
import { useRouter } from "next/navigation";
import {
  bookNowTimingSnapshot,
  markBookNowTiming,
  startBookNowTiming,
} from "@/features/flight-results/utils/book-now-timing";
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


/** Clean Book Now → Traveler UI state boundaries (JP-DEEP-CLOSURE-01). */
export const BOOK_NOW_UI_PHASE = {
  VALIDATING_FARE: "VALIDATING_FARE",
  PREPARING_TRAVELER: "PREPARING_TRAVELER",
  NAVIGATING_TO_TRAVELER: "NAVIGATING_TO_TRAVELER",
} as const;
export type BookNowUiPhase = (typeof BOOK_NOW_UI_PHASE)[keyof typeof BOOK_NOW_UI_PHASE];

const PHASE_USER_MESSAGE: Record<BookNowUiPhase, string> = {
  VALIDATING_FARE: "Checking the latest fare and availability",
  PREPARING_TRAVELER: "Preparing traveler details",
  NAVIGATING_TO_TRAVELER: "Almost there",
};

type PassengersUrlAuthorityStamp = {
  present: boolean;
  url: string;
  source: "server_revalidate" | "server_handoff";
  search_id?: string | null;
  at: number;
};

function stampPassengersUrlAuthority(url: string, source: PassengersUrlAuthorityStamp["source"]) {
  if (typeof window === "undefined" || !url) return;
  let searchId: string | null = null;
  try {
    searchId = new URL(url, window.location.origin).searchParams.get("search_id");
  } catch {
    searchId = null;
  }
  const stamp: PassengersUrlAuthorityStamp = {
    present: true,
    url,
    source,
    search_id: searchId,
    at: Date.now(),
  };
  (window as Window & { __jpPassengersUrlAuthority?: PassengersUrlAuthorityStamp }).__jpPassengersUrlAuthority =
    stamp;
  try {
    sessionStorage.setItem("jp-passengers-url-authority", JSON.stringify(stamp));
  } catch {
    /* ignore */
  }
}

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

/** Ensure return-combo checkout query mirrors select-return-combo handoff fields. */
function enrichReturnComboPassengersUrl(url: string, params: RevalidationParams): string {
  if (!params.isReturnCombo) return url;
  try {
    const parsed = new URL(url, typeof window !== "undefined" ? window.location.origin : "https://jetpakistan.pk");
    const setIfMissing = (key: string, value?: string) => {
      const v = (value ?? "").trim();
      if (!v) return;
      if (!parsed.searchParams.get(key)) parsed.searchParams.set(key, v);
    };
    setIfMissing("combo_id", params.comboId);
    setIfMissing("outbound_key", params.outboundKey);
    const returnFare = (params.returnFareOptionKey ?? params.fareOptionKey ?? "").trim();
    setIfMissing("fare_option_key", returnFare);
    setIfMissing("return_fare_option_key", returnFare);
    setIfMissing("outbound_fare_option_key", params.outboundFareOptionKey);
    return `${parsed.pathname}${parsed.search}`;
  } catch {
    return url;
  }
}

export function useRevalidation() {
  const router = useRouter();
  const [state, setState] = useState<RevalidationState>("idle");
  const [uiPhase, setUiPhase] = useState<BookNowUiPhase | null>(null);
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

  const applyUiPhase = useCallback((phase: BookNowUiPhase) => {
    setUiPhase(phase);
    setMessage(PHASE_USER_MESSAGE[phase]);
  }, []);

  const reset = useCallback(() => {
    setState("idle");
    setUiPhase(null);
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
    const message = (body?.message ?? "").toLowerCase();
    if (status === 410 || apiStatus.includes("expired") || apiStatus === "search_expired") return "expired";
    if (
      apiStatus.includes("timeout") ||
      apiStatus.includes("timed_out") ||
      status === 408 ||
      status === 504 ||
      message.includes("temporarily")
    ) {
      return "timeout";
    }
    if (
      apiStatus.includes("unavailable") ||
      apiStatus === "offer_not_found" ||
      apiStatus === "offer_stale" ||
      apiStatus === "selected_fare_resolution_failed" ||
      status === 404
    ) {
      return "unavailable";
    }
    if (status >= 500 || status === 429) return "timeout";
    // Fresh offer + generic failed often means supplier temporary revalidation gap.
    if (apiStatus === "failed" && body?.offer_freshness?.offer_freshness_status === "fresh") {
      return "timeout";
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
    applyUiPhase(BOOK_NOW_UI_PHASE.PREPARING_TRAVELER);
    markBookNowTiming("T4A_checkout_prep_start", { ui_phase: BOOK_NOW_UI_PHASE.PREPARING_TRAVELER });
    stampPassengersUrlAuthority(resolved, "server_handoff");
    markBookNowTiming("T4C_passengers_url_ready", { handoff: resolved.slice(0, 120) });
    applyUiPhase(BOOK_NOW_UI_PHASE.NAVIGATING_TO_TRAVELER);
    markBookNowTiming("T6_nav_start", {
      ui_phase: BOOK_NOW_UI_PHASE.NAVIGATING_TO_TRAVELER,
      handoff: resolved.slice(0, 120),
    });

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

    // JP-NEXT-PERF-02B: soft router.push to /booking/passengers can hang while
    // results still has in-flight logos saturating the connection pool.
    // Hard assign after releasing *image* slots recovers FE overhead.
    // JP-APP-PERF-CLOSURE-01: do NOT window.stop() or strip preload/prefetch —
    // that cancelled the passengers document/chunk warmup and produced
    // TRAVELER_ROUTE_SHELL_P95≈4.5s on otherwise warm browsers.
    const isPassengersHandoff =
      resolved.startsWith("/booking/passengers") ||
      /(?:^|\/)booking\/passengers(?:\?|$)/.test(resolved);
    if (isPassengersHandoff) {
      const target = resolved.startsWith("http")
        ? resolved
        : resolved.startsWith("/")
          ? resolved
          : `/${resolved}`;
      const absolute = target.startsWith("http") ? target : `${window.location.origin}${target}`;

      const releaseImageSlots = () => {
        try {
          document.querySelectorAll("img").forEach((node) => {
            const img = node as HTMLImageElement;
            img.removeAttribute("srcset");
            img.removeAttribute("src");
            try {
              img.src = "";
            } catch {
              /* ignore */
            }
          });
        } catch {
          /* ignore */
        }
      };

      try {
        void router.prefetch(target.startsWith("http") ? new URL(target).pathname + new URL(target).search : target);
      } catch {
        /* prefetch is best-effort */
      }

      persistTimingForContinuity();
      markBookNowTiming("T4B_checkout_prep_done");
      markBookNowTiming("T5_router_push", { nav: "hard_assign_image_release" });
      markBookNowTiming("T7_passenger_route", { nav: "hard_assign_image_release" });
      persistTimingForContinuity();
      releaseImageSlots();
      // Hard assign remains authoritative for passengers_url handoff (soft push raced
      // fallback assign and produced hangs / destroyed contexts in 01R smoke).
      try {
        window.location.assign(absolute);
      } catch {
        window.location.href = absolute;
      }
      return true;
    }
    window.location.assign(resolved);
    return true;
  }, [applyUiPhase, router]);

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
        ui_phase: BOOK_NOW_UI_PHASE.VALIDATING_FARE,
        offerId: params.offerId,
        provider: params.supplierProvider,
        acceptFareChange,
      });
      try {
        void router.prefetch("/booking/passengers");
      } catch {
        /* best-effort warmup during supplier revalidation */
      }
      void promise.then((result) => {
        markBookNowTiming("T3_revalidate_response", {
          ok: result.ok,
          total_ms: result.timing?.total_ms,
          request_ms: result.timing?.request_ms,
          csrf_ms: result.timing?.csrf_ms,
          supplier_ms: result.timing?.supplier_ms ?? undefined,
          laravel_other_ms: result.timing?.laravel_other_ms ?? undefined,
        });
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
      // R7D: warm Return combos too — drawer open overlaps live revalidate / rematch.
      const key = `${paramsCacheKey(params)}|accept=0`;
      if (warmPromiseRef.current?.key === key) return;
      lastParamsRef.current = params;
      markBookNowTiming("T2_revalidate_start", {
        ui_phase: BOOK_NOW_UI_PHASE.VALIDATING_FARE,
        offerId: params.offerId,
        provider: params.supplierProvider,
        phase: "warm",
        return_combo: Boolean(params.isReturnCombo),
      });
      // Warm Traveler route/chunks while drawer revalidation runs (hard nav still authoritative).
      try {
        void router.prefetch("/booking/passengers");
      } catch {
        /* best-effort */
      }
      const promise = revalidateOffer({
        searchId: params.searchId,
        offerId: params.offerId,
        selectedFareOptionId: params.fareOptionKey,
        acceptFareChange: false,
      });
      warmPromiseRef.current = { key, promise };
      void promise.then((result) => {
        markBookNowTiming("T3_revalidate_response", {
          phase: "warm",
          ok: result.ok,
          total_ms: result.timing?.total_ms,
          request_ms: result.timing?.request_ms,
          csrf_ms: result.timing?.csrf_ms,
          supplier_ms: result.timing?.supplier_ms ?? undefined,
          laravel_other_ms: result.timing?.laravel_other_ms ?? undefined,
        });
        if (warmPromiseRef.current?.key !== key) return;
        if (!result.ok) return;
        const change = extractFareChange(result.data);
        if (change) {
          pendingHandoffRef.current = result.data.passengers_url
            ? enrichReturnComboPassengersUrl(result.data.passengers_url, params)
            : null;
        }
      });
      return;
    },
    [extractFareChange, router],
  );

  const continueToPassengers = useCallback(
    async (params: RevalidationParams) => {
      if (inFlightRef.current) return;
      inFlightRef.current = true;
      // T0 = Book Now / Continue click (not drawer open).
      startBookNowTiming({
        phase: "continue_click",
        offerId: params.offerId,
        searchId: params.searchId,
      });
      // Flush processing transition before awaiting supplier work so ACK paint
      // is not deferred behind the first revalidation network hop (ACK P95 gate).
      flushSync(() => {
        setState("loading");
        applyUiPhase(BOOK_NOW_UI_PHASE.VALIDATING_FARE);
        setFareChange(null);
      });
      // Synchronous ACK stamp (performance.now) — harness reads this, not Playwright selector RTT.
      try {
        const session = typeof window !== "undefined" ? window.__jpBookNowTiming : null;
        const ackMs =
          session && typeof session.t0 === "number"
            ? Math.round(performance.now() - session.t0)
            : 0;
        (window as Window & { __jpFareAckMs?: number }).__jpFareAckMs = ackMs;
        document.documentElement.setAttribute("data-jp-fare-processing", "1");
        document.documentElement.setAttribute("data-jp-fare-ack-ms", String(ackMs));
      } catch {
        /* ignore */
      }
      markBookNowTiming("T1_handler", { phase: "continueToPassengers" });
      // Warm Traveler route/chunk while fare revalidation runs (hard nav still authoritative).
      try {
        router.prefetch("/booking/passengers");
      } catch {
        /* ignore */
      }
      lastParamsRef.current = params;

      try {
        // Return combos still need a bounded read-only reprice of the selected outbound
        // offer before checkout handoff when the provider requires it.
        if (params.isReturnCombo && params.comboId && params.outboundKey) {
          if (providerRequiresRevalidation(params.supplierProvider)) {
            applyUiPhase(BOOK_NOW_UI_PHASE.VALIDATING_FARE);
            const progressTimer = window.setTimeout(() => {
              setMessage("We're still confirming this fare with the airline.");
            }, 8000);
            let result: Awaited<ReturnType<typeof runRevalidation>>;
            try {
              result = await runRevalidation(params, false);
            } finally {
              window.clearTimeout(progressTimer);
            }
            if (!result.ok) {
              const failureState = classifyFailure(result.status, result.data);
              setState(failureState);
              setMessage(result.message);
              return;
            }
            const change = extractFareChange(result.data);
            markBookNowTiming("T3A_payload_classified", {
              fare_changed: Boolean(change),
              price_changed: Boolean(change),
              decision_required: Boolean(change),
              return_combo: true,
            });
            if (change) {
              const enriched = result.data.passengers_url
                ? enrichReturnComboPassengersUrl(result.data.passengers_url, params)
                : null;
              pendingHandoffRef.current = enriched;
              markBookNowTiming("T3B_fare_change_decision", { fare_changed: true });
              markBookNowTiming("T3C_fare_modal_requested", { fare_changed: true });
              setFareChange({ ...change, passengersUrl: enriched ?? change.passengersUrl });
              setUiPhase(null);
              setState("fare_change");
              return;
            }
            // R7D: after successful revalidation the API already returns passengers_url
            // with draft authority. Prefer soft handoff — do NOT full-document POST
            // select-return-combo (that path caused the ~55–80s customer tail).
            const passengersUrl = result.data.passengers_url
              ? enrichReturnComboPassengersUrl(result.data.passengers_url, params)
              : null;
            if (passengersUrl) {
              stampPassengersUrlAuthority(passengersUrl, "server_revalidate");
              applyUiPhase(BOOK_NOW_UI_PHASE.PREPARING_TRAVELER);
              markBookNowTiming("T4_draft_prep_start", { phase: "return_combo_passengers_url" });
              const ok = await navigateHandoff(
                passengersUrl,
                params.fareOptionKey || result.data.selected_fare_option_id || undefined,
                params.searchId,
              );
              if (!ok) {
                return;
              }
              markBookNowTiming("T5_draft_prep_done", { phase: "return_combo_passengers_url" });
              setState("success");
              return;
            }
            applyUiPhase(BOOK_NOW_UI_PHASE.VALIDATING_FARE);
          }
          markResultsLeftForCheckout(params.searchId);
          markBookNowTiming("T4_draft_prep_start", { phase: "return_combo_form_fallback" });
          await submitReturnComboSelection({
            searchId: params.searchId,
            comboId: params.comboId,
            outboundKey: params.outboundKey,
            fareOptionKey: params.fareOptionKey,
            returnFareOptionKey: params.returnFareOptionKey ?? params.fareOptionKey,
            outboundFareOptionKey: params.outboundFareOptionKey,
          });
          markBookNowTiming("T5_draft_prep_done", { phase: "return_combo_form_fallback" });
          return;
        }

        const needsRevalidation = providerRequiresRevalidation(params.supplierProvider);

        if (needsRevalidation) {
          applyUiPhase(BOOK_NOW_UI_PHASE.VALIDATING_FARE);
          const progressTimer = window.setTimeout(() => {
            setMessage("We're still confirming this fare with the airline.");
          }, 8000);
          let result: Awaited<ReturnType<typeof runRevalidation>>;
          try {
            result = await runRevalidation(params, false);
          } finally {
            window.clearTimeout(progressTimer);
          }

          if (!result.ok) {
            const failureState = classifyFailure(result.status, result.data);
            setState(failureState);
            setMessage(result.message);
            return;
          }

          const change = extractFareChange(result.data);
          markBookNowTiming("T3A_payload_classified", {
            fare_changed: Boolean(change),
            price_changed: Boolean(change),
            decision_required: Boolean(change),
          });
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
            markBookNowTiming("T3B_fare_change_decision", { fare_changed: true });
            markBookNowTiming("T3C_fare_modal_requested", { fare_changed: true });
            setFareChange(change);
            setUiPhase(null);
            setState("fare_change");
            return;
          }

          const passengersUrl = result.data.passengers_url;
          if (passengersUrl) {
            stampPassengersUrlAuthority(passengersUrl, "server_revalidate");
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
    [applyUiPhase, classifyFailure, extractFareChange, navigateHandoff, router, runRevalidation],
  );

  const acceptFareChange = useCallback(async () => {
    if (inFlightRef.current) return;
    const params = lastParamsRef.current;
    if (!params) {
      setState("error");
      setMessage("Unable to accept the updated fare. Please try again.");
      return;
    }

    markBookNowTiming("T3F_fare_accept_handler", { fare_changed: true });

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
          markBookNowTiming("T3B_fare_change_decision", { fare_changed: true, second: true });
          markBookNowTiming("T3C_fare_modal_requested", { fare_changed: true, second: true });
          setFareChange(secondChange);
          setUiPhase(null);
          setState("fare_change");
          setMessage("The fare changed again. Please review the updated price.");
          return;
        }

        markBookNowTiming("T3G_fare_accept_complete", { fare_changed: true });

        const rawPassengers =
          result.data.passengers_url ?? pendingHandoffRef.current;
        const passengersUrl = rawPassengers
          ? enrichReturnComboPassengersUrl(rawPassengers, params)
          : null;

        if (passengersUrl) {
          if (!(await navigateHandoff(passengersUrl, params.fareOptionKey, params.searchId))) {
            setState("error");
            setMessage("Unable to accept the updated fare. Please try again.");
            return;
          }
          setState("success");
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

        setState("error");
        setMessage("Unable to accept the updated fare. Please try again.");
        return;
      }

      markBookNowTiming("T3G_fare_accept_complete", { fare_changed: true, no_revalidate: true });
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
    uiPhase,
    message,
    fareChange,
    continueToPassengers,
    acceptFareChange,
    warmStartRevalidation,
    reset,
  };
}
