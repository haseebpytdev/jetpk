"use client";

import { useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { fetchReturnOptionsData, submitReturnComboSelection } from "@/features/flight-results/services/flight-results-api";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";
import { SearchErrorState } from "@/features/flight-results/components/SearchErrorState";
import { ExpiredSearchState } from "@/features/flight-results/components/ExpiredSearchState";
import { PriceBlock } from "@/features/flight-results/components/PriceBlock";

export function ReturnOptionsPage() {
  const searchParams = useSearchParams();
  const searchId = searchParams.get("search_id") ?? "";
  const outboundKey = searchParams.get("outbound_key") ?? "";
  const [status, setStatus] = useState<"loading" | "ready" | "error" | "expired">("loading");
  const [message, setMessage] = useState("");
  const [options, setOptions] = useState<Array<Record<string, unknown>>>([]);
  const [selectingId, setSelectingId] = useState<string | null>(null);

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
      setOptions(response.data.return_options ?? []);
      setStatus("ready");
    });

    return () => controller.abort();
  }, [outboundKey, searchId]);

  const handleSelect = (combo: Record<string, unknown>) => {
    const comboId = String(combo.combo_id ?? "");
    if (!comboId || selectingId) return;
    setSelectingId(comboId);
    void submitReturnComboSelection({
      searchId,
      comboId,
      outboundKey,
      fareOptionKey: combo.fare_option_key ? String(combo.fare_option_key) : undefined,
    });
  };

  return (
    <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
      <h1 className="text-2xl font-semibold text-jp-text">Choose return flight</h1>
      {status === "loading" ? <ResultSkeleton count={3} /> : null}
      {status === "error" ? <SearchErrorState message={message} /> : null}
      {status === "expired" ? <ExpiredSearchState message={message} /> : null}
      {status === "ready" ? (
        <div className="space-y-4" role="list" aria-label="Return flight options">
          {options.map((option) => {
            const comboId = String(option.combo_id ?? "");
            const price = option.displayed_price as number | undefined;
            return (
              <article key={comboId} className="rounded-jp-card border border-jp-border bg-jp-surface p-4" role="listitem">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p className="font-medium text-jp-text">Return option</p>
                    <p className="text-sm text-jp-text-muted">
                      {(option.return_journey_display as { departure_time_display?: string } | undefined)?.departure_time_display ?? ""}
                      {" → "}
                      {(option.return_journey_display as { arrival_time_display?: string } | undefined)?.arrival_time_display ?? ""}
                    </p>
                  </div>
                  <PriceBlock
                    amount={price}
                    priceDisplay={option.price_display as string | undefined}
                    loading={selectingId === comboId}
                    onSelect={() => handleSelect(option)}
                  />
                </div>
              </article>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}
