import { HOMEPAGE_V2_WHY } from "../fixtures";

export function HomepageV2Why() {
  return (
    <section className="jp-hp-section jp-hp-section--compact jp-homepage-v2__container" data-testid="jp-hp-why">
      <h2>Why JetPakistan?</h2>
      <div className="jp-hp-why">
        {HOMEPAGE_V2_WHY.map((item) => (
          <article key={item.id} className="jp-hp-why__item" data-testid="jp-hp-why-item">
            <i className="jp-hp-why__icon" aria-hidden="true">
              {item.icon}
            </i>
            <div>
              <strong>{item.title}</strong>
              <small>{item.description}</small>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
