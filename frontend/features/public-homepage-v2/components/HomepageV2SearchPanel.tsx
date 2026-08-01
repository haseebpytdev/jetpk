"use client";

import { useState } from "react";
import { PublicButton } from "@/features/public-theme-v2";
import { HOMEPAGE_V2_SEARCH } from "../fixtures";

export function HomepageV2SearchPanel() {
  const [activeTab, setActiveTab] = useState<string>(HOMEPAGE_V2_SEARCH.tabs[0]);

  return (
    <div className="jp-homepage-v2__container jp-hp-search-dock">
      <section
        className="jp-hp-search-panel"
        aria-label="Flight search visual fixture"
        data-testid="jp-hp-search-panel"
        data-review-fixture="true"
      >
        <div className="jp-hp-search-tabs" role="tablist" aria-label="Trip type">
          {HOMEPAGE_V2_SEARCH.tabs.map((tab) => (
            <button
              key={tab}
              type="button"
              role="tab"
              aria-selected={activeTab === tab}
              className={activeTab === tab ? "jp-hp-search-tabs__active" : ""}
              onClick={() => setActiveTab(tab)}
              data-review-fixture="true"
            >
              {tab}
            </button>
          ))}
        </div>

        <div className="jp-hp-search-fields">
          <div className="jp-hp-search-field">
            <small>FROM</small>
            <strong>
              {HOMEPAGE_V2_SEARCH.origin.code} · {HOMEPAGE_V2_SEARCH.origin.city}
            </strong>
            <span>{HOMEPAGE_V2_SEARCH.origin.airport}</span>
          </div>

          <button
            type="button"
            className="jp-hp-search-swap"
            aria-label="Swap airports fixture"
            data-review-fixture="true"
            disabled
          >
            ⇅
          </button>

          <div className="jp-hp-search-field">
            <small>TO</small>
            <strong>
              {HOMEPAGE_V2_SEARCH.destination.code} · {HOMEPAGE_V2_SEARCH.destination.city}
            </strong>
            <span>{HOMEPAGE_V2_SEARCH.destination.airport}</span>
          </div>

          <div className="jp-hp-search-field">
            <small>DEPARTURE</small>
            <strong>{HOMEPAGE_V2_SEARCH.departure.date}</strong>
            <span>{HOMEPAGE_V2_SEARCH.departure.day}</span>
          </div>

          <div className="jp-hp-search-field">
            <small>PASSENGERS &amp; CLASS</small>
            <strong>{HOMEPAGE_V2_SEARCH.passengers.count}</strong>
            <span>{HOMEPAGE_V2_SEARCH.passengers.cabin}</span>
          </div>

          <PublicButton
            variant="primary"
            className="jp-hp-search-cta"
            type="button"
            data-testid="jp-hp-search-cta"
            data-review-fixture="true"
            onClick={(e) => e.preventDefault()}
          >
            {HOMEPAGE_V2_SEARCH.cta} ⌕
          </PublicButton>
        </div>

        <p className="jp-hp-search-fixture-note">
          Visual-only search fixture — does not submit to Laravel or suppliers.
        </p>
      </section>
    </div>
  );
}
