"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { FlightDetailsDrawer, type FlightDetailsContext } from "@/features/flight-details";
import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { fetchReturnOptionsData } from "@/features/flight-results/services/flight-results-api";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";
import { SearchErrorState } from "@/features/flight-results/components/SearchErrorState";
import { ExpiredSearchState } from "@/features/flight-results/components/ExpiredSearchState";
import { SearchProgress } from "@/features/flight-results/components/SearchProgress";
import { AirlineIdentity } from "@/features/flight-results/components/AirlineIdentity";
import { normalizeJourneyDisplay } from "@/features/flight-results/utils/normalize-journey-display";
import { FareBadge } from "@/features/flight-results/components/FareBadge";
import { FlightResultActions } from "@/features/flight-results/components/FlightResultActions";
import { SupplierSourceBadge } from "@/features/flight-results/components/SupplierSourceBadge";
import { TimeRouteBlock } from "@/features/flight-results/components/TimeRouteBlock";
import type { FareFamilyOption, FlightOffer } from "@/features/flight-results/types";
import { formatWholePkr } from "@/features/flight-results/utils/price";
import { mergeProgressiveReturnOptions } from "@/features/flight-results/utils/merge-return-options";

function resolveJourneyDisplay(option: Record<string, unknown>) {
  const raw = option.journey_display ?? option.return_journey_display;
  if (!raw || typeof raw !== "object") return null;
  return normalizeJourneyDisplay(raw as Record<string, unknown>, {
    airline_code: typeof option.airline_code === "string" ? option.airline_code : undefined,
    airline_name: typeof option.airline_name === "string" ? option.airline_name : undefined,
    airline_logo_url: typeof option.airline_logo_url === "string" ? option.airline_logo_url : null,
  });
}

function resolveFareOptions(option: Record<string, unknown>): FareFamilyOption[] {
  const branded = option.branded_fares_display_options ?? option.fare_family_options_display;
  return Array.isArray(branded) ? (branded as FareFamilyOption[]) : [];
}

function returnOptionToOffer(option: Record<string, unknown>, comboId: string): FlightOffer {
  const journey = resolveJourneyDisplay(option);
  const price =
    (option.displayed_price as number | undefined)
    ?? (option.total_amount as number | undefined);
  return {
    offer_id: comboId,
    airline_code: journey?.airline_code,
    airline_name: journey?.airline_name,
    airline_logo_url: journey?.airline_logo_url,
    flight_number: journey?.flight_number,
    departure_time: journey?.departure_time_display,
    arrival_time: journey?.arrival_time_display,
    departure_airport_code: journey?.origin_airport_code,
    arrival_airport_code: journey?.destination_airport_code,
    duration: journey?.duration_display,
    stops: journey?.stops ?? 0,
    stops_label_display: journey?.stops_label_display,
    layover_summary_display: journey?.layover_summary_display,
    arrival_day_offset_display: journey?.arrival_day_offset_display,
    displayed_price: price,
    price_display: (option.price_display as string | undefined) ?? (option.total_display as string | undefined),
    final_customer_price: price,
    supplier_source_label: typeof option.supplier_source_label === "string" ? option.supplier_source_label : undefined,
    can_book: option.can_book !== false,
    refundable: typeof option.refundable === "boolean" ? option.refundable : undefined,
    branded_fares_display_options: resolveFareOptions(option),
    fare_family_options_display: resolveFareOptions(option),
    segments: [
      {
        origin_airport_code: journey?.origin_airport_code,
        destination_airport_code: journey?.destination_airport_code,
        departure_time_display: journey?.departure_time_display,
        arrival_time_display: journey?.arrival_time_display,
        duration_display: journey?.duration_display,
        airline_code: journey?.airline_code,
        airline_name: journey?.airline_name,
        airline_logo_url: journey?.airline_logo_url,
        flight_number: journey?.flight_number,
        arrival_day_offset_display: journey?.arrival_day_offset_display,
      },
    ],
  };
}

export function ReturnOptionsPage() {
  const searchParams = useSearchParams();
  const searchId = searchParams.get("search_id") ?? "";
  const outboundKey = searchParams.get("outbound_key") ?? "";
  const outboundFareOptionKey = searchParams.get("outbound_fare_option_key") ?? "";
  const [status, setStatus] = useState<"loading" | "ready" | "error" | "expired" | "empty">("loading");
  const [message, setMessage] = useState("");
  const [options, setOptions] = useState<Array<Record<string, unknown>>>([]);
  const [selectedFareByCombo, setSelectedFareByCombo] = useState<Record<string, string>>({});
  const [detailsContext, setDetailsContext] = useState<FlightDetailsContext | null>(null);
  const detailsTriggerRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!searchId || !outboundKey) {
      setStatus("error");
      setMessage("Missing return search details.");
      return;
    }

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | null = null;
    const controller = new AbortController();

    const isActiveSearch = (pipeline: string): boolean => {
      const normalized = pipeline.toLowerCase();
      return (
        normalized === "queued" ||
        normalized === "searching" ||
        normalized === "partial" ||
        normalized === "in_progress"
      );
    };

    const load = async () => {
      const response = await fetchReturnOptionsData({ searchId, outboundKey, signal: controller.signal });
      if (cancelled || controller.signal.aborted) return;
      if (!response.ok) {
        setStatus(response.status === 410 ? "expired" : "error");
        setMessage(response.message);
        return;
      }
      const list = response.data.return_options ?? [];
      const pipeline = String(response.data.status ?? "").toLowerCase();
      const terminal =
        pipeline === "ready" ||
        pipeline === "empty" ||
        pipeline === "failed" ||
        pipeline === "expired" ||
        pipeline === "error";

      if (list.length > 0 || terminal) {
        setOptions((current) => mergeProgressiveReturnOptions(current, list, pipeline));
        if (list.length > 0) {
          const defaults: Record<string, string> = {};
          list.forEach((option) => {
            const comboId = String(option.combo_id ?? "");
            const fares = resolveFareOptions(option);
            if (comboId && fares[0]?.option_key) {
              defaults[comboId] = fares[0].option_key;
            }
          });
          setSelectedFareByCombo((prev) => (terminal ? { ...defaults } : { ...defaults, ...prev }));
          setStatus("ready");
          setMessage("");
          if (isActiveSearch(pipeline)) {
            timer = setTimeout(() => {
              if (!cancelled) void load();
            }, 750);
          }
          return;
        }
      }

      if (isActiveSearch(pipeline)) {
        setStatus("loading");
        setMessage("Finding return flights for your selected outbound…");
        timer = setTimeout(() => {
          if (!cancelled) void load();
        }, 750);
        return;
      }

      setOptions([]);
      setStatus("empty");
      setMessage(response.data.empty_message ?? "No return flights match this outbound selection.");
    };

    setStatus("loading");
    setMessage("Finding return flights for your selected outbound…");
    void load();

    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
      controller.abort();
    };
  }, [outboundKey, searchId]);

  const openFareConfirmation = (combo: Record<string, unknown>, intent: "details" | "booking" = "booking") => {
    const comboId = String(combo.combo_id ?? "");
    if (!comboId) return;
    const fareOptions = resolveFareOptions(combo);
    const rawReturnKey =
      selectedFareByCombo[comboId]
      ?? String(combo.fare_option_key ?? "")
      ?? "";
    const returnFareKey = resolveAuthoritativeFareOptionKey(rawReturnKey, fareOptions) ?? rawReturnKey;
    const offer = returnOptionToOffer(combo, comboId);

    setDetailsContext({
      searchId,
      offerId: comboId,
      comboId,
      outboundKey,
      outboundFareOptionKey: outboundFareOptionKey || undefined,
      fareOptionKey: returnFareKey || undefined,
      initialOffer: offer,
      initialFareOptions: fareOptions,
      intent,
      legMode: "return_confirm",
    });
  };

  const emptyState = useMemo(
    () => (
      <div className="rounded-jp-card border border-jp-border bg-jp-surface p-6" data-testid="return-options-empty">
        <p className="font-medium text-jp-text">{message || "No return flights are available for this outbound."}</p>
      </div>
    ),
    [message],
  );

  return (
    <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
      <h1 className="text-2xl font-semibold text-jp-text">Choose return flight</h1>
      {outboundFareOptionKey ? (
        <p className="text-sm text-jp-text-muted" data-testid="outbound-fare-preserved">
          Outbound branded fare preserved for checkout.
        </p>
      ) : null}
      {status === "loading" ? (
        <>
          <SearchProgress message={message || "Finding return flights for your selected outbound…"} />
          <ResultSkeleton count={3} />
        </>
      ) : null}
      {status === "error" ? <SearchErrorState message={message} /> : null}
      {status === "expired" ? <ExpiredSearchState message={message} /> : null}
      {status === "empty" ? emptyState : null}
      {status === "ready" ? (
        <div className="space-y-4" role="list" aria-label="Return flight options">
          {options.map((option) => {
            const comboId = String(option.combo_id ?? "");
            const fareOptions = resolveFareOptions(option);
            const selectedKey = selectedFareByCombo[comboId] ?? fareOptions[0]?.option_key ?? "";
            const selectedFare = fareOptions.find((item) => item.option_key === selectedKey);
            const price =
              selectedFare?.displayed_price
              ?? (option.displayed_price as number | undefined)
              ?? (option.total_amount as number | undefined);
            const journey = resolveJourneyDisplay(option);
            const supplierLabel =
              typeof option.supplier_source_label === "string" ? option.supplier_source_label : undefined;
            const refundable = typeof option.refundable === "boolean" ? option.refundable : undefined;
            return (
              <article
                key={comboId}
                className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card transition-all hover:border-jp-primary/30 hover:shadow-md sm:px-4"
                role="listitem"
                data-testid="return-option-card"
              >
                <div className="grid items-stretch gap-3 lg:grid-cols-[minmax(8rem,0.85fr)_minmax(0,2fr)_minmax(10.5rem,0.95fr)] lg:items-center lg:gap-4">
                  <div className="min-w-0">
                    <AirlineIdentity
                      code={journey?.airline_code}
                      name={journey?.airline_name}
                      logoUrl={journey?.airline_logo_url}
                      size="md"
                    />
                  </div>
                  <div className="min-w-0 space-y-2">
                    <TimeRouteBlock
                      departureTime={journey?.departure_time_display}
                      arrivalTime={journey?.arrival_time_display}
                      arrivalDayOffset={journey?.arrival_day_offset_display}
                      originCode={journey?.origin_airport_code}
                      destinationCode={journey?.destination_airport_code}
                      duration={journey?.duration_display}
                      stops={journey?.stops ?? 0}
                      stopsLabel={journey?.stops_label_display}
                      layoverSummary={journey?.layover_summary_display}
                    />
                    <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                      <FareBadge refundable={refundable} />
                      <SupplierSourceBadge label={supplierLabel} />
                    </div>
                    <p className="sr-only" data-testid="return-option-times">
                      {journey?.departure_time_display ?? ""} → {journey?.arrival_time_display ?? ""}
                    </p>
                  </div>
                  <div className="flex min-w-0 flex-wrap items-end justify-between gap-3 border-t border-jp-border-soft pt-3 lg:flex-col lg:items-end lg:border-l lg:border-t-0 lg:pl-3 lg:pt-0">
                    <div className="min-w-0 text-left sm:text-right">
                      <p className="text-[11px] uppercase tracking-wide text-jp-text-muted">Total fare</p>
                      <p className="text-lg font-bold leading-tight text-jp-text break-words" data-testid="result-price-display">
                        {formatWholePkr(price)
                          ?? (selectedFare?.price_display as string | undefined)
                          ?? (option.price_display as string | undefined)
                          ?? (option.total_display as string | undefined)
                          ?? "Price unavailable"}
                      </p>
                    </div>
                    <FlightResultActions
                      onDetails={() => {
                        detailsTriggerRef.current = document.activeElement as HTMLButtonElement | null;
                        openFareConfirmation(option, "details");
                      }}
                      onBook={() => {
                        detailsTriggerRef.current = document.activeElement as HTMLButtonElement | null;
                        openFareConfirmation(option, "booking");
                      }}
                      detailsTestId="return-details-trigger"
                      bookTestId="result-price-button"
                    />
                  </div>
                </div>
              </article>
            );
          })}
        </div>
      ) : null}

      <FlightDetailsDrawer
        open={detailsContext !== null}
        context={detailsContext}
        onClose={() => setDetailsContext(null)}
        triggerRef={detailsTriggerRef}
      />
    </div>
  );
}
