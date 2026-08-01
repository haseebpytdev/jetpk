import { HOMEPAGE_V2_INSPIRATION } from "../fixtures";

export function HomepageV2Inspiration() {
  return (
    <section className="jp-hp-section jp-homepage-v2__container" data-testid="jp-hp-inspiration">
      <div className="jp-hp-section-heading">
        <div>
          <h2>Travel Inspiration</h2>
          <p>Stories, guides and tips — fixture content for layout review.</p>
        </div>
        <button type="button" className="jp-hp-section-heading__link" data-review-fixture="true" aria-disabled="true">
          View all articles →
        </button>
      </div>

      <div className="jp-hp-inspiration">
        {HOMEPAGE_V2_INSPIRATION.map((item) => (
          <article key={item.id} className="jp-hp-inspiration-card" data-testid="jp-hp-inspiration-card">
            <div className="jp-hp-inspiration-card__image">
              <div
                className={`jp-hp-image-slot jp-hp-image-slot--${item.imageVariant}`}
                data-asset-state="missing"
                aria-hidden="true"
              />
            </div>
            <small>{item.category}</small>
            <strong>{item.title}</strong>
            <span>{item.meta}</span>
          </article>
        ))}
      </div>
    </section>
  );
}
