"use client";

import "./styles/homepage-v2.css";
import { HomepageV2Header } from "./components/HomepageV2Header";
import { HomepageV2Hero } from "./components/HomepageV2Hero";
import { HomepageV2SearchPanel } from "./components/HomepageV2SearchPanel";
import { HomepageV2BenefitStrip } from "./components/HomepageV2BenefitStrip";
import { HomepageV2DiscoverDivider } from "./components/HomepageV2DiscoverDivider";
import { HomepageV2Destinations } from "./components/HomepageV2Destinations";
import { HomepageV2Offers } from "./components/HomepageV2Offers";
import { HomepageV2Why } from "./components/HomepageV2Why";
import { HomepageV2SupportCallout } from "./components/HomepageV2SupportCallout";
import { HomepageV2Inspiration } from "./components/HomepageV2Inspiration";
import { HomepageV2Footer } from "./components/HomepageV2Footer";

export function HomepageV2Composition() {
  return (
    <div className="jp-homepage-v2" data-testid="jp-homepage-v2-composition">
      <p className="jp-homepage-v2__banner" role="status">
        Development review route — static composition only. Not connected to search, CMS, or booking.
      </p>

      <HomepageV2Header />

      <main>
        <HomepageV2Hero />
        <HomepageV2SearchPanel />
        <HomepageV2BenefitStrip />
        <HomepageV2DiscoverDivider />
        <HomepageV2Destinations />
        <HomepageV2Offers />
        <HomepageV2Why />
        <HomepageV2SupportCallout />
        <HomepageV2Inspiration />
      </main>

      <HomepageV2Footer />
    </div>
  );
}
