import { HOMEPAGE_V2_DESTINATIONS } from "../fixtures";

export function HomepageV2Destinations() {
  return (
    <section className="jp-hp-section jp-homepage-v2__container" data-testid="jp-hp-destinations">
      <div className="jp-hp-section-heading">
        <div>
          <h2>Destinations on the Rise</h2>
          <p>Fixture destination cards for layout review.</p>
        </div>
        <button type="button" className="jp-hp-section-heading__link" data-review-fixture="true" aria-disabled="true">
          View all destinations →
        </button>
      </div>

      <div className="jp-hp-destinations">
        {HOMEPAGE_V2_DESTINATIONS.map((dest) => (
          <article key={dest.id} className="jp-hp-destination-card" data-testid="jp-hp-destination-card">
            <div className="jp-hp-destination-card__image">
              <div
                className={`jp-hp-image-slot jp-hp-image-slot--${dest.imageVariant}`}
                data-asset-state="missing"
                aria-hidden="true"
              />
            </div>
            <div className="jp-hp-destination-card__body">
              <strong>{dest.route}</strong>
              <small>From</small>
              <b>{dest.priceLabel}</b>
              <span>{dest.airlineLabel}</span>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
