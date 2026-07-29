"use client";

import { useRouter } from "next/navigation";
import type { OutboundOption } from "../types";
import { formatDisplayPrice } from "../utils/price";
import { AirlineIdentity } from "./AirlineIdentity";
import { PriceBlock } from "./PriceBlock";
import { StopsAndLayover } from "./StopsAndLayover";
import { TimeRouteBlock } from "./TimeRouteBlock";

type OutboundOptionCardProps = {
  option: OutboundOption;
  searchId: string;
};

export function OutboundOptionCard({ option, searchId }: OutboundOptionCardProps) {
  const router = useRouter();
  const journey = option.journey_display;

  const handleSelect = () => {
    router.push(`/flights/return-options?search_id=${encodeURIComponent(searchId)}&outbound_key=${encodeURIComponent(option.outbound_key)}`);
  };

  return (
    <article className="rounded-jp-card border border-jp-border bg-jp-surface p-4 shadow-jp-card" data-testid="outbound-option-card">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="space-y-3">
          <AirlineIdentity
            code={journey?.airline_code}
            name={journey?.airline_name}
            logoUrl={journey?.airline_logo_url}
            size="lg"
          />
          <TimeRouteBlock
            departureTime={journey?.departure_time_display}
            arrivalTime={journey?.arrival_time_display}
            arrivalDayOffset={journey?.arrival_day_offset_display}
            originCode={journey?.origin_airport_code}
            destinationCode={journey?.destination_airport_code}
            duration={journey?.duration_display}
          />
          <StopsAndLayover stops={journey?.stops ?? 0} stopsLabel={journey?.stops_label_display} layoverSummary={journey?.layover_summary_display} />
          {option.combo_count ? (
            <p className="text-xs text-jp-text-muted">{option.combo_count} return option{option.combo_count === 1 ? "" : "s"}</p>
          ) : null}
        </div>
        <div className="flex flex-col items-stretch gap-1 sm:items-end">
          <p className="text-xs text-jp-text-muted">From total return fare</p>
          <PriceBlock
            amount={option.from_total_amount}
            priceDisplay={option.from_total_display ?? formatDisplayPrice(option.from_total_amount)}
            onSelect={handleSelect}
          />
        </div>
      </div>
    </article>
  );
}
