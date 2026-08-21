"use client";

import { useState } from "react";
import type { FlightOffer } from "@/features/flight-results/types";
import type { FallbackDetailsSection } from "../types";
import { buildFareRouteLabel } from "../utils/route-label";
import { BaggageDetails } from "./BaggageDetails";
import { FareRulesAccordion } from "./FareRulesAccordion";
import { PriceBreakdown } from "./PriceBreakdown";

type TabKey = "baggage" | "policy" | "details";

export function FareSummaryTabs({
  offer,
  fallback,
  initialTab = "baggage",
}: {
  offer: FlightOffer;
  fallback?: FallbackDetailsSection | null;
  initialTab?: TabKey;
}) {
  const [active, setActive] = useState<TabKey>(initialTab);
  const routeLabel = buildFareRouteLabel(offer);
  const tabs: Array<{ key: TabKey; label: string }> = [
    { key: "baggage", label: "Baggage Policy" },
    { key: "policy", label: "Fare Policy" },
    { key: "details", label: "Fare Details" },
  ];

  return (
    <section className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface" data-testid="fare-summary-tabs">
      <div className="border-b border-jp-border px-3.5 pt-3.5">
        <h3 className="text-base font-semibold text-jp-text">Fare Summary</h3>
        <div className="mt-2.5 flex gap-1 overflow-x-auto" role="tablist" aria-label="Selected fare summary">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              role="tab"
              aria-selected={active === tab.key}
              className={`whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary ${
                active === tab.key
                  ? "border-jp-primary text-jp-primary"
                  : "border-transparent text-jp-text-muted hover:text-jp-text"
              }`}
              onClick={() => setActive(tab.key)}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>
      <div className="p-3.5" role="tabpanel">
        {active === "baggage" ? (
          <BaggageDetails
            baggage={fallback?.baggage}
            summaryDisplay={offer.baggage_summary_display ?? offer.baggage}
            checkedDisplay={offer.baggage_checked_display}
            cabinDisplay={offer.baggage_cabin_display}
            routeLabel={routeLabel}
          />
        ) : null}
        {active === "policy" ? (
          <FareRulesAccordion
            rules={fallback?.fare_rules}
            refundRule={offer.refund_rule}
            changeRule={offer.change_rule}
            refundable={offer.refundable}
            routeLabel={routeLabel}
          />
        ) : null}
        {active === "details" ? <PriceBreakdown offer={offer} breakdown={fallback?.fare_breakdown} /> : null}
      </div>
    </section>
  );
}
