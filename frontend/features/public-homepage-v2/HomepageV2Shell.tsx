"use client";

import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { useThemeV2 } from "@/features/public-theme-v2";
import {
  HOMEPAGE_V2_BENEFITS,
  HOMEPAGE_V2_DESTINATIONS,
  HOMEPAGE_V2_FOOTER_COLUMNS,
  HOMEPAGE_V2_INSPIRATION,
  HOMEPAGE_V2_NAV,
  HOMEPAGE_V2_OFFERS,
  HOMEPAGE_V2_SEARCH,
  HOMEPAGE_V2_WHY,
} from "./fixtures";
import "./styles/homepage-shell.css";

export function HomepageV2Shell() {
  const searchParams = useSearchParams();
  const isCapture = searchParams.get("capture") === "1";
  const { theme, toggleTheme } = useThemeV2();
  const [activeTab, setActiveTab] = useState<string>(HOMEPAGE_V2_SEARCH.tabs[0]);

  return (
    <div
      className="jp-homepage-v2"
      data-testid="jp-homepage-v2-composition"
      data-capture={isCapture ? "true" : "false"}
      data-review-route="jetpk-homepage-v2"
    >
      {!isCapture ? (
        <span className="jp-homepage-v2__dev-marker" data-review-fixture="true">
          Dev review
        </span>
      ) : null}

      <div className="hp-app-shell">
        <header className="hp-site-header" data-testid="jp-hp-header" data-landmark="header">
          <div className="hp-container hp-header-inner">
            <button type="button" className="hp-brand" data-review-fixture="true" aria-disabled="true">
              <span className="hp-brand-mark" aria-hidden="true">
                ↗
              </span>
              <span>
                JetPakistan
                <span className="hp-brand-tagline">FLY SMART, FLY EASY</span>
              </span>
            </button>

            <nav className="hp-main-nav" aria-label="Primary navigation">
              {HOMEPAGE_V2_NAV.map((item) => (
                <span key={item.label} style={{ display: "inline-flex", alignItems: "center" }}>
                  <button
                    type="button"
                    className="hp-nav-item"
                    data-review-fixture="true"
                    aria-disabled="true"
                  >
                    {item.label}
                    {item.dropdown ? " ⌄" : ""}
                  </button>
                  {"badge" in item && item.badge ? (
                    <span className="hp-new-pill">{item.badge}</span>
                  ) : null}
                </span>
              ))}
            </nav>

            <div className="hp-header-actions">
              <button
                type="button"
                className="hp-theme-toggle"
                onClick={toggleTheme}
                data-testid="jp-hp-theme-toggle"
                aria-label={theme === "light" ? "Switch to dark theme" : "Switch to light theme"}
              >
                {theme === "light" ? "☾" : "☀"}
              </button>
              <button
                type="button"
                className="hp-control-button"
                data-review-fixture="true"
                aria-disabled="true"
              >
                🇵🇰 PKR ⌄
              </button>
              <button
                type="button"
                className="hp-login-link"
                data-review-fixture="true"
                aria-disabled="true"
              >
                Log in / Sign up
              </button>
              <button
                type="button"
                className="hp-button hp-button-outline hp-button-small"
                data-review-fixture="true"
                aria-disabled="true"
              >
                Book Now
              </button>
            </div>
          </div>
        </header>

        <main>
          <section className="hp-home-hero" data-testid="jp-hp-hero" data-landmark="hero" aria-label="Hero">
            <div className="hp-container hp-home-hero-inner">
              <div className="hp-home-hero-copy">
                <h1>
                  Explore the World
                  <br />
                  with <span>JetPakistan</span>
                </h1>
                <p>
                  Find the best flight deals to your dream destinations. Book with confidence and fly
                  with ease.
                </p>
              </div>
              <div className="hp-home-hero-art">
                <div
                  className="hp-home-hero-art-slot"
                  data-asset-state="missing"
                  data-testid="jp-hp-hero-art-slot"
                  aria-label="Hero aircraft image slot"
                />
              </div>
            </div>
          </section>

          <div className="hp-container hp-search-dock">
            <section
              className="hp-search-panel"
              aria-label="Flight search visual fixture"
              data-testid="jp-hp-search-panel"
              data-landmark="search"
              data-review-fixture="true"
            >
              <div className="hp-search-tabs" role="tablist" aria-label="Trip type">
                {HOMEPAGE_V2_SEARCH.tabs.map((tab) => (
                  <button
                    key={tab}
                    type="button"
                    role="tab"
                    aria-selected={activeTab === tab}
                    className={activeTab === tab ? "hp-active" : ""}
                    onClick={() => setActiveTab(tab)}
                    data-review-fixture="true"
                  >
                    {tab}
                  </button>
                ))}
              </div>
              <div className="hp-search-fields">
                <div className="hp-search-field">
                  <small>FROM</small>
                  <strong>
                    {HOMEPAGE_V2_SEARCH.origin.code} · {HOMEPAGE_V2_SEARCH.origin.city}
                  </strong>
                  <span>{HOMEPAGE_V2_SEARCH.origin.airport}</span>
                </div>
                <button
                  type="button"
                  className="hp-swap-button"
                  aria-label="Swap airports"
                  data-review-fixture="true"
                  aria-disabled="true"
                >
                  ⇅
                </button>
                <div className="hp-search-field">
                  <small>TO</small>
                  <strong>
                    {HOMEPAGE_V2_SEARCH.destination.code} · {HOMEPAGE_V2_SEARCH.destination.city}
                  </strong>
                  <span>{HOMEPAGE_V2_SEARCH.destination.airport}</span>
                </div>
                <div className="hp-search-field">
                  <small>DEPARTURE</small>
                  <strong>{HOMEPAGE_V2_SEARCH.departure.date}</strong>
                  <span>{HOMEPAGE_V2_SEARCH.departure.day}</span>
                </div>
                <div className="hp-search-field">
                  <small>PASSENGERS &amp; CLASS</small>
                  <strong>{HOMEPAGE_V2_SEARCH.passengers.count}</strong>
                  <span>{HOMEPAGE_V2_SEARCH.passengers.cabin}</span>
                </div>
                <button
                  type="button"
                  className="hp-button hp-button-primary hp-button-small hp-search-button"
                  data-testid="jp-hp-search-cta"
                  data-review-fixture="true"
                >
                  Search Flights ⌕
                </button>
              </div>
            </section>
          </div>

          <section
            className="hp-benefit-strip hp-container"
            data-testid="jp-hp-benefits"
            data-landmark="benefits"
            aria-label="Benefits"
          >
            {HOMEPAGE_V2_BENEFITS.map((item) => (
              <div key={item.title} data-testid="jp-hp-benefit-item">
                <i aria-hidden="true">{item.icon}</i>
                <span>
                  <strong>{item.title}</strong>
                  <small>{item.description}</small>
                </span>
              </div>
            ))}
          </section>

          <div className="hp-discover-line hp-container" data-testid="jp-hp-discover" data-landmark="discover">
            <span aria-hidden="true">●</span>
            <div />
            <strong>Scroll to Discover</strong>
            <div />
            <span aria-hidden="true">✈</span>
          </div>

          <section
            className="hp-section hp-container"
            data-testid="jp-hp-destinations"
            data-landmark="destinations"
          >
            <div className="hp-section-heading">
              <div>
                <h2>Destinations on the Rise</h2>
                <p>Trending routes loved by travelers just like you.</p>
              </div>
              <button type="button" className="hp-section-link" data-review-fixture="true" aria-disabled="true">
                View all destinations →
              </button>
            </div>
            <div className="hp-destination-grid">
              {HOMEPAGE_V2_DESTINATIONS.map((dest) => (
                <article
                  key={`${dest.from}-${dest.to}`}
                  className="hp-destination-card"
                  data-testid="jp-hp-destination-card"
                >
                  <div
                    className={`hp-image-placeholder hp-${dest.imageClass}`}
                    data-asset-state="missing"
                    aria-hidden="true"
                  />
                  <div>
                    <strong>
                      {dest.from} → {dest.to}
                    </strong>
                    <small>From</small>
                    <b>{dest.price}</b>
                    <span>{dest.airline}</span>
                  </div>
                </article>
              ))}
            </div>
          </section>

          <section className="hp-section hp-container" data-testid="jp-hp-offers" data-landmark="offers">
            <div className="hp-section-heading">
              <div>
                <h2>Featured Offers</h2>
                <p>Limited-time deals on top destinations.</p>
              </div>
              <button type="button" className="hp-section-link" data-review-fixture="true" aria-disabled="true">
                View all offers →
              </button>
            </div>
            <div className="hp-offer-grid">
              {HOMEPAGE_V2_OFFERS.map((offer) => (
                <article
                  key={offer.title}
                  className={`hp-offer-card hp-offer-${offer.variant}`}
                  data-testid="jp-hp-offer-card"
                >
                  <div>
                    <small>UP TO</small>
                    <strong>{offer.discount}</strong>
                    <span>{offer.caption}</span>
                    <button
                      type="button"
                      className={`hp-button hp-button-small ${offer.variant === 3 ? "hp-button-primary" : "hp-button-light"}`}
                      data-review-fixture="true"
                      aria-disabled="true"
                    >
                      Book Now
                    </button>
                  </div>
                  <div className="hp-offer-visual" data-asset-state="missing" aria-hidden="true" />
                </article>
              ))}
            </div>
          </section>

          <section
            className="hp-section hp-container hp-compact-section"
            data-testid="jp-hp-why"
            data-landmark="why"
          >
            <h2>Why JetPakistan?</h2>
            <div className="hp-why-grid">
              {HOMEPAGE_V2_WHY.map((item) => (
                <article key={item.title} data-testid="jp-hp-why-item">
                  <i aria-hidden="true">{item.icon}</i>
                  <div>
                    <strong>{item.title}</strong>
                    <small>{item.description}</small>
                  </div>
                </article>
              ))}
            </div>
          </section>

          <section
            className="hp-support-callout hp-container"
            data-testid="jp-hp-support"
            data-landmark="support"
          >
            <div className="hp-support-icon" aria-hidden="true">
              ◉
            </div>
            <div>
              <h2>Need Help? We&apos;re Here for You</h2>
              <p>Our support team is available 24/7 to assist with bookings, changes and more.</p>
            </div>
            <button
              type="button"
              className="hp-button hp-button-primary hp-button-small"
              data-review-fixture="true"
              aria-disabled="true"
            >
              Contact Support →
            </button>
          </section>

          <section
            className="hp-section hp-container"
            data-testid="jp-hp-inspiration"
            data-landmark="inspiration"
          >
            <div className="hp-section-heading">
              <div>
                <h2>Travel Inspiration</h2>
                <p>Stories, guides and tips to fuel your next adventure.</p>
              </div>
              <button type="button" className="hp-section-link" data-review-fixture="true" aria-disabled="true">
                View all articles →
              </button>
            </div>
            <div className="hp-article-grid">
              {HOMEPAGE_V2_INSPIRATION.map((item) => (
                <article key={item.title} className="hp-article-card" data-testid="jp-hp-inspiration-card">
                  <div
                    className={`hp-article-image hp-${item.imageClass}`}
                    data-asset-state="missing"
                    aria-hidden="true"
                  />
                  <small>{item.category}</small>
                  <strong>{item.title}</strong>
                  <span>{item.meta}</span>
                </article>
              ))}
            </div>
          </section>
        </main>

        <footer className="hp-site-footer" data-testid="jp-hp-footer" data-landmark="footer">
          <div className="hp-container hp-footer-grid">
            <div className="hp-footer-brand">
              <div className="hp-brand hp-brand-footer">
                <span className="hp-brand-mark" aria-hidden="true">
                  ↗
                </span>
                <span>JetPakistan</span>
              </div>
              <p>Connecting you to the world with comfort, care and convenience.</p>
              <div className="hp-social-row" aria-label="Social placeholders">
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
                    className="hp-footer-link"
                    data-review-fixture="true"
                    aria-disabled="true"
                  >
                    {link}
                  </button>
                ))}
              </div>
            ))}
            <div className="hp-stay-updated">
              <h3>Stay Updated</h3>
              <p>Newsletter fixture — non-operational review control.</p>
              <div className="hp-newsletter-row">
                <input
                  type="email"
                  placeholder="Enter your email"
                  readOnly
                  data-review-fixture="true"
                  aria-label="Email fixture"
                />
                <button
                  type="button"
                  className="hp-button hp-button-light hp-button-small"
                  data-review-fixture="true"
                  aria-disabled="true"
                >
                  Subscribe
                </button>
              </div>
            </div>
          </div>
          <div className="hp-container hp-footer-bottom">
            <span>© {new Date().getFullYear()} JetPakistan. All rights reserved.</span>
            <span>Made with ♥ in Pakistan.</span>
          </div>
        </footer>
      </div>
    </div>
  );
}
