import { HOMEPAGE_V2_BENEFITS } from "../fixtures";

export function HomepageV2BenefitStrip() {
  return (
    <section
      className="jp-homepage-v2__container jp-hp-benefits"
      data-testid="jp-hp-benefits"
      aria-label="Benefits"
    >
      {HOMEPAGE_V2_BENEFITS.map((item) => (
        <div key={item.id} className="jp-hp-benefits__item" data-testid="jp-hp-benefit-item">
          <i className="jp-hp-benefits__icon" aria-hidden="true">
            {item.icon}
          </i>
          <span>
            <strong>{item.title}</strong>
            <small>{item.description}</small>
          </span>
        </div>
      ))}
    </section>
  );
}
