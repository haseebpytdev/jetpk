import { PublicButton } from "@/features/public-theme-v2";
import { HOMEPAGE_V2_SUPPORT } from "../fixtures";

export function HomepageV2SupportCallout() {
  return (
    <section className="jp-homepage-v2__container jp-hp-support" data-testid="jp-hp-support">
      <div className="jp-hp-support__icon" aria-hidden="true">
        ◉
      </div>
      <div>
        <h2>{HOMEPAGE_V2_SUPPORT.title}</h2>
        <p>{HOMEPAGE_V2_SUPPORT.description}</p>
      </div>
      <PublicButton variant="primary" type="button" data-review-fixture="true" disabled>
        {HOMEPAGE_V2_SUPPORT.cta} →
      </PublicButton>
    </section>
  );
}
