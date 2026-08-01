import { HOMEPAGE_V2_HERO } from "../fixtures";

export function HomepageV2Hero() {
  return (
    <section className="jp-hp-hero" data-testid="jp-hp-hero" aria-label="Hero">
      <div className="jp-homepage-v2__container jp-hp-hero__inner">
        <div className="jp-hp-hero__copy">
          <h1>
            {HOMEPAGE_V2_HERO.title}
            <br />
            <span>{HOMEPAGE_V2_HERO.titleAccent}</span>
          </h1>
          <p>{HOMEPAGE_V2_HERO.supporting}</p>
        </div>
        <div className="jp-hp-hero__art">
          <div
            className="jp-hp-hero__art-slot"
            data-asset-state="missing"
            data-testid="jp-hp-hero-art-slot"
            aria-label="Hero aircraft image slot"
          >
            <span className="jp-hp-hero__art-glyph" aria-hidden="true">
              ✈
            </span>
          </div>
        </div>
      </div>
    </section>
  );
}
