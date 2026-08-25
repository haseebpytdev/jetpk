"use client";

import { resolveAuthoritativeFareOptionKey } from "@/features/flight-details/utils/fare-option-key";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import type { FareFamilyOption, OutboundOption } from "../types";
import { formatDisplayPrice } from "../utils/price";
import { AirlineIdentity } from "./AirlineIdentity";
import { BrandedFareCarousel } from "./BrandedFareCarousel";
import { PriceBlock } from "./PriceBlock";
import { StopsAndLayover } from "./StopsAndLayover";
import { SupplierSourceBadge } from "./SupplierSourceBadge";
import { TimeRouteBlock } from "./TimeRouteBlock";

type OutboundOptionCardProps = {
  option: OutboundOption;
  searchId: string;
};

function resolveFareOptions(option: OutboundOption): FareFamilyOption[] {
  return option.branded_fares_display_options ?? option.fare_family_options_display ?? [];
}

export function OutboundOptionCard({ option, searchId }: OutboundOptionCardProps) {
  const router = useRouter();
  const journey = option.journey_display;
  const fareOptions = useMemo(() => resolveFareOptions(option), [option]);
  const [selectedFareKey, setSelectedFareKey] = useState(() => fareOptions[0]?.option_key ?? "");
  const selectedOption = fareOptions.find((item) => item.option_key === selectedFareKey) ?? fareOptions[0];
  const effectiveFareKey = selectedOption?.option_key ?? selectedFareKey;

  const handleSelect = (fareKey?: string) => {
    const key = resolveAuthoritativeFareOptionKey(fareKey ?? effectiveFareKey, fareOptions);
    const qs = new URLSearchParams({
      search_id: searchId,
      outbound_key: option.outbound_key,
    });
    if (key) qs.set("outbound_fare_option_key", key);
    router.push(`/flights/return-options?${qs.toString()}`);
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
          <SupplierSourceBadge label={option.supplier_source_label} />
        </div>
        <div className="flex flex-col items-stretch gap-1 sm:items-end">
          <p className="text-xs text-jp-text-muted">From total return fare</p>
          <PriceBlock
            amount={selectedOption?.displayed_price ?? option.from_total_amount}
            priceDisplay={
              selectedOption?.price_display
              ?? option.from_total_display
              ?? formatDisplayPrice(option.from_total_amount)
            }
            onSelect={() => handleSelect()}
          />
        </div>
      </div>
      {fareOptions.length > 1 ? (
        <BrandedFareCarousel
          options={fareOptions}
          selectedKey={effectiveFareKey}
          onSelect={setSelectedFareKey}
          onBook={(optionKey) => handleSelect(optionKey)}
        />
      ) : null}
    </article>
  );
}
