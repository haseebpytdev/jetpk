import { HOMEPAGE_V2_FOOTER_COLUMNS } from "../fixtures";

export function HomepageV2Footer() {
  return (
    <footer className="jp-hp-footer" data-testid="jp-hp-footer">
      <div className="jp-homepage-v2__container jp-hp-footer__grid">
        <div className="jp-hp-footer__brand">
          <div className="jp-hp-footer__brand-name">
            <span className="jp-hp-header__brand-mark" aria-hidden="true">
              ↗
            </span>
            <span>JetPakistan</span>
          </div>
          <p>Fixture tagline for footer layout review.</p>
          <div className="jp-hp-footer__social" aria-label="Social placeholders">
            <span>f</span>
            <span>◎</span>
            <span>◉</span>
            <span>in</span>
            <span>▶</span>
          </div>
        </div>

        {HOMEPAGE_V2_FOOTER_COLUMNS.map((col) => (
          <div key={col.title}>
            <h3>{col.title}</h3>
            {col.links.map((link) => (
              <button
                key={link}
                type="button"
                className="jp-hp-footer__link"
                data-review-fixture="true"
                aria-disabled="true"
              >
                {link}
              </button>
            ))}
          </div>
        ))}

        <div className="jp-hp-footer__newsletter">
          <h3>Stay Updated</h3>
          <p>Newsletter fixture — non-operational review control.</p>
          <div className="jp-hp-footer__newsletter-form">
            <input
              type="email"
              placeholder="Enter your email"
              readOnly
              data-review-fixture="true"
              aria-label="Email fixture"
            />
            <button
              type="button"
              className="jp-v2-btn jp-v2-btn--secondary"
              data-review-fixture="true"
              aria-disabled="true"
              style={{ color: "var(--jp-v2-brand)", background: "white" }}
            >
              Subscribe
            </button>
          </div>
        </div>
      </div>

      <div className="jp-homepage-v2__container jp-hp-footer__bottom">
        <span>© {new Date().getFullYear()} JetPakistan. Review fixture.</span>
        <span>Development composition — not production</span>
      </div>
    </footer>
  );
}
