"use client";

import type { PairedReturnOption } from "../types";
import { TimeRouteBlock } from "./TimeRouteBlock";

type PairReturnCardProps = {
  option: PairedReturnOption;
  onSelect: (option: PairedReturnOption) => void;
  onDetails?: (option: PairedReturnOption) => void;
  selecting?: boolean;
};

function journeyTimes(journey?: Record<string, unknown>) {
  return {
    dep: String(journey?.departure_time_display ?? journey?.departure_time ?? "—"),
    arr: String(journey?.arrival_time_display ?? journey?.arrival_time ?? "—"),
    origin: String(journey?.origin_airport_code ?? journey?.origin ?? "—"),
    dest: String(journey?.destination_airport_code ?? journey?.destination ?? "—"),
    duration: String(journey?.duration_display ?? journey?.duration ?? ""),
    stops: String(journey?.stops_label_display ?? ""),
    offset: typeof journey?.arrival_day_offset_display === "string" ? journey.arrival_day_offset_display : undefined,
  };
}

export function PairReturnCard({ option, onSelect, onDetails, selecting }: PairReturnCardProps) {
  const outbound = journeyTimes(option.outbound_journey);
  const inbound = journeyTimes(option.return_journey);

  return (
    <article
      className="rounded-jp-card border border-jp-border bg-jp-surface p-4"
      data-testid="pair-return-card"
    >
      <p className="text-xs font-medium uppercase tracking-wide text-jp-text-muted">Outbound</p>
      <TimeRouteBlock
        departureTime={outbound.dep}
        arrivalTime={outbound.arr}
        originCode={outbound.origin}
        destinationCode={outbound.dest}
        duration={outbound.duration}
        arrivalDayOffset={outbound.offset}
      />
      {outbound.stops ? <p className="mt-1 text-xs text-jp-text-muted">{outbound.stops}</p> : null}

      <p className="mt-3 text-xs font-medium uppercase tracking-wide text-jp-text-muted">Return</p>
      <TimeRouteBlock
        departureTime={inbound.dep}
        arrivalTime={inbound.arr}
        originCode={inbound.origin}
        destinationCode={inbound.dest}
        duration={inbound.duration}
        arrivalDayOffset={inbound.offset}
      />
      {inbound.stops ? <p className="mt-1 text-xs text-jp-text-muted">{inbound.stops}</p> : null}

      <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="text-lg font-semibold text-jp-text">{option.total_display ?? "Fare unavailable"}</p>
          <p className="text-xs text-jp-text-muted">
            {[option.airline_name, option.fare_family, option.cabin, option.baggage].filter(Boolean).join(" · ")}
          </p>
        </div>
        <div className="flex gap-2">
          {onDetails ? (
            <button
              type="button"
              className="rounded-jp-md border border-jp-border px-3 py-2 text-sm"
              onClick={() => onDetails(option)}
            >
              Details
            </button>
          ) : null}
          <button
            type="button"
            className="rounded-jp-md bg-jp-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-60"
            disabled={!option.can_book || selecting}
            onClick={() => onSelect(option)}
            data-testid="pair-select"
          >
            {selecting ? "…" : "Select"}
          </button>
        </div>
      </div>
    </article>
  );
}
