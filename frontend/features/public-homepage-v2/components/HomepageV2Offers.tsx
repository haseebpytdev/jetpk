import { PublicButton } from "@/features/public-theme-v2";
import { HOMEPAGE_V2_OFFERS } from "../fixtures";

export function HomepageV2Offers() {
  return (
    <section className="jp-hp-section jp-homepage-v2__container" data-testid="jp-hp-offers">
      <div className="jp-hp-section-heading">
        <div>
          <h2>Featured Offers</h2>
          <p>Limited-time visual fixtures for layout development.</p>
        </div>
        <button type="button" className="jp-hp-section-heading__link" data-review-fixture="true" aria-disabled="true">
          View all offers →
        </button>
      </div>

      <div className="jp-hp-offers">
        {HOMEPAGE_V2_OFFERS.map((offer) => (
          <article
            key={offer.id}
            className={`jp-hp-offer-card jp-hp-offer-card--${offer.variant}`}
            data-testid="jp-hp-offer-card"
          >
            <div>
              <small>UP TO</small>
              <strong>{offer.discount}</strong>
              <span>{offer.caption}</span>
              <PublicButton
                variant="secondary"
                type="button"
                data-review-fixture="true"
                disabled
                style={
                  offer.variant === 3
                    ? { background: "var(--jp-v2-brand)", color: "white", minHeight: "36px", fontSize: "0.8rem" }
                    : { background: "white", color: "var(--jp-v2-brand-dark)", minHeight: "36px", fontSize: "0.8rem" }
                }
              >
                Book Now
              </PublicButton>
            </div>
            <div className="jp-hp-offer-card__visual" data-asset-state="missing" aria-hidden="true" />
          </article>
        ))}
      </div>
    </section>
  );
}
