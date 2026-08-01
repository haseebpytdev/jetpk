"use client";

import { PublicButton } from "@/features/public-theme-v2";
import { PublicIconButton } from "@/features/public-theme-v2";
import { useThemeV2 } from "@/features/public-theme-v2";
import { HOMEPAGE_V2_NAV } from "../fixtures";

export function HomepageV2Header() {
  const { toggleTheme } = useThemeV2();

  return (
    <header className="jp-hp-header" data-testid="jp-hp-header">
      <div className="jp-homepage-v2__container jp-hp-header__inner">
        <div className="jp-hp-header__brand" aria-label="JetPakistan">
          <span className="jp-hp-header__brand-mark" aria-hidden="true">
            ↗
          </span>
          <span>
            JetPakistan
            <span className="jp-hp-header__tagline">FLY SMART, FLY EASY</span>
          </span>
        </div>

        <nav className="jp-hp-header__nav" aria-label="Primary navigation">
          {HOMEPAGE_V2_NAV.map((item) => (
            <span key={item.label} style={{ display: "inline-flex", alignItems: "center" }}>
              <button
                type="button"
                className="jp-hp-header__nav-item"
                data-review-fixture="true"
                aria-disabled="true"
              >
                {item.label}
                {item.hasDropdown ? " ⌄" : ""}
              </button>
              {"badge" in item && item.badge ? (
                <span className="jp-hp-header__badge">{item.badge}</span>
              ) : null}
            </span>
          ))}
        </nav>

        <div className="jp-hp-header__actions">
          <PublicIconButton
            label="Switch theme"
            onClick={toggleTheme}
            data-testid="jp-hp-theme-toggle"
            className="jp-v2-header__theme-btn"
          >
            <span aria-hidden="true">☾</span>
          </PublicIconButton>

          <button
            type="button"
            className="jp-hp-header__currency"
            data-review-fixture="true"
            aria-disabled="true"
          >
            🏳 Review currency ⌄
          </button>

          <button
            type="button"
            className="jp-hp-header__login"
            data-review-fixture="true"
            aria-disabled="true"
          >
            Log in / Sign up
          </button>

          <PublicButton
            variant="secondary"
            className="jp-hp-header__book"
            data-review-fixture="true"
            disabled
          >
            Book Now
          </PublicButton>

          <PublicIconButton
            label="Open menu"
            className="jp-hp-header__menu-btn"
            data-review-fixture="true"
            disabled
          >
            <span aria-hidden="true">☰</span>
          </PublicIconButton>
        </div>
      </div>
    </header>
  );
}
