"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { FlightDetailsDrawer, type FlightDetailsContext } from "@/features/flight-details";
import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { fetchReturnOptionsData, submitReturnComboSelection } from "@/features/flight-results/services/flight-results-api";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";
import { SearchErrorState } from "@/features/flight-results/components/SearchErrorState";
import { ExpiredSearchState } from "@/features/flight-results/components/ExpiredSearchState";
import { BrandedFareCarousel } from "@/features/flight-results/components/BrandedFareCarousel";
import { PriceBlock } from "@/features/flight-results/components/PriceBlock";
import type { FareFamilyOption } from "@/features/flight-results/types";

type JourneyDisplay = {
  departure_time_display?: string;
  arrival_time_display?: string;
};

function resolveJourneyDisplay(option: Record<string, unknown>): JourneyDisplay | undefined {
  const journey = option.journey_display ?? option.return_journey_display;
  return journey && typeof journey === "object" ? (journey as JourneyDisplay) : undefined;
}

function resolveFareOptions(option: Record<string, unknown>): FareFamilyOption[] {
  const branded = option.branded_fares_display_options ?? option.fare_family_options_display;
  return Array.isArray(branded) ? (branded as FareFamilyOption[]) : [];
}

export function ReturnOptionsPage() {
  const searchParams = useSearchParams();
  const searchId = searchParams.get("search_id") ?? "";
  const outboundKey = searchParams.get("outbound_key") ?? "";
  const outboundFareOptionKey = searchParams.get("outbound_fare_option_key") ?? "";
  const [status, setStatus] = useState<"loading" | "ready" | "error" | "expired" | "empty">("loading");
  const [message, setMessage] = useState("");
  const [options, setOptions] = useState<Array<Record<string, unknown>>>([]);
  const [selectingId, setSelectingId] = useState<string | null>(null);
  const [selectedFareByCombo, setSelectedFareByCombo] = useState<Record<string, string>>({});
  const [detailsContext, setDetailsContext] = useState<FlightDetailsContext | null>(null);
  const detailsTriggerRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!searchId || !outboundKey) {
      setStatus("error");
      setMessage("Missing return search details.");
      return;
    }

    const controller = new AbortController();
    void fetchReturnOptionsData({ searchId, outboundKey, signal: controller.signal }).then((response) => {
      if (controller.signal.aborted) return;
      if (!response.ok) {
        setStatus(response.status === 410 ? "expired" : "error");
        setMessage(response.message);
        return;
      }
      const list = response.data.return_options ?? [];
      setOptions(list);
      const defaults: Record<string, string> = {};
      list.forEach((option) => {
        const comboId = String(option.combo_id ?? "");
        const fares = resolveFareOptions(option);
        if (comboId && fares[0]?.option_key) {
          defaults[comboId] = fares[0].option_key;
        }
      });
      setSelectedFareByCombo(defaults);
      setStatus(list.length === 0 ? "empty" : "ready");
      if (list.length === 0) {
        setMessage(response.data.empty_message ?? "No return flights match this outbound selection.");
      }
    });

    return () => controller.abort();
  }, [outboundKey, searchId]);

  const handleSelect = (combo: Record<string, unknown>) => {
    const comboId = String(combo.combo_id ?? "");
    if (!comboId || selectingId) return;
    const fareOptions = resolveFareOptions(combo);
    const rawReturnKey = selectedFareByCombo[comboId] ?? String(combo.fare_option_key ?? "");
    const returnFareKey = resolveAuthoritativeFareOptionKey(rawReturnKey, fareOptions) ?? rawReturnKey;
    const outboundKeyAuth = outboundFareOptionKey.trim();

    setSelectingId(comboId);
    void submitReturnComboSelection({
      searchId,
      comboId,
      outboundKey,
      fareOptionKey: returnFareKey || undefined,
      returnFareOptionKey: returnFareKey || undefined,
      outboundFareOptionKey: outboundKeyAuth || undefined,
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
      {status === "loading" ? <ResultSkeleton count={3} /> : null}
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
            const price = selectedFare?.displayed_price ?? (option.displayed_price as number | undefined) ?? (option.total_amount as number | undefined);
            const journey = resolveJourneyDisplay(option);
            return (
              <article key={comboId} className="rounded-jp-card border border-jp-border bg-jp-surface p-4" role="listitem" data-testid="return-option-card">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p className="font-medium text-jp-text">Return option</p>
                    <p className="text-sm text-jp-text-muted" data-testid="return-option-times">
                      {journey?.departure_time_display ?? ""}
                      {" → "}
                      {journey?.arrival_time_display ?? ""}
                    </p>
                    {selectedFare?.name || selectedFare?.brand_name ? (
                      <p className="mt-1 text-xs font-medium text-jp-text" data-testid="return-selected-brand">
                        Return fare: {selectedFare.name ?? selectedFare.brand_name}
                      </p>
                    ) : null}
                    <button
                      type="button"
                      className="mt-2 text-sm font-medium text-jp-primary underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
                      data-testid="return-details-trigger"
                      onClick={(event) => {
                        detailsTriggerRef.current = event.currentTarget;
                        setDetailsContext({
                          searchId,
                          offerId: comboId,
                          comboId,
                          outboundKey,
                          fareOptionKey: selectedKey || undefined,
                        });
                      }}
                    >
                      Details
                    </button>
                  </div>
                  <PriceBlock
                    amount={price}
                    priceDisplay={(selectedFare?.price_display as string | undefined) ?? (option.price_display as string | undefined) ?? (option.total_display as string | undefined)}
                    loading={selectingId === comboId}
                    onSelect={() => handleSelect(option)}
                  />
                </div>
                {fareOptions.length > 1 ? (
                  <BrandedFareCarousel
                    options={fareOptions}
                    selectedKey={selectedKey}
                    onSelect={(optionKey) => setSelectedFareByCombo((current) => ({ ...current, [comboId]: optionKey }))}
                    onBook={(optionKey) => {
                      setSelectedFareByCombo((current) => ({ ...current, [comboId]: optionKey }));
                      handleSelect({ ...option, fare_option_key: optionKey });
                    }}
                    bookingOptionKey={selectingId === comboId ? selectedKey : null}
                  />
                ) : null}
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
