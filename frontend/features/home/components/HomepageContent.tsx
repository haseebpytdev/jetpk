import { DestinationsSection } from "./DestinationsSection";
import { FeaturedOffersSection } from "./FeaturedOffersSection";
import { HomepageHero } from "./HomepageHero";
import { SupportBanner } from "./SupportBanner";
import { TravelInspirationSection } from "./TravelInspirationSection";
import { WhyJetPakistanSection } from "./WhyJetPakistanSection";

export function HomepageContent() {
  return (
    <>
      <HomepageHero />
      <DestinationsSection />
      <FeaturedOffersSection />
      <WhyJetPakistanSection />
      <SupportBanner />
      <TravelInspirationSection />
    </>
  );
}
