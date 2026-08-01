"use client";

import Link from "next/link";
import { useCallback, useId, useState } from "react";
import { PublicButton } from "./PublicButton";
import { PublicIconButton } from "./PublicIconButton";
import { PublicContainer } from "./PublicContainer";
import { useThemeV2 } from "./PublicThemeV2Root";

const NAV_LINKS = [
  { label: "Flights", href: "/#flight-search" },
  { label: "About", href: "/about-us" },
  { label: "Support", href: "/support" },
] as const;

export function PublicHeaderPrototype() {
  const { theme, toggleTheme } = useThemeV2();
  const [menuOpen, setMenuOpen] = useState(false);
  const menuId = useId();

  const closeMenu = useCallback(() => setMenuOpen(false), []);

  return (
    <header className="jp-v2-header">
      <PublicContainer>
        <div className="jp-v2-header__inner">
          <Link href="/" className="jp-v2-header__brand" onClick={closeMenu}>
            JetPakistan
          </Link>

          <nav className="jp-v2-header__nav jp-v2-header__nav--desktop" aria-label="Primary">
            <ul className="jp-v2-header__nav-list">
              {NAV_LINKS.map((link) => (
                <li key={link.href}>
                  <Link href={link.href}>{link.label}</Link>
                </li>
              ))}
            </ul>
          </nav>

          <div className="jp-v2-header__actions">
            <PublicIconButton
              label={theme === "light" ? "Switch to dark theme" : "Switch to light theme"}
              onClick={toggleTheme}
              data-testid="jp-v2-theme-toggle"
              className="jp-v2-header__theme-btn"
            >
              <span aria-hidden="true">{theme === "light" ? "☾" : "☀"}</span>
            </PublicIconButton>

            <div className="jp-v2-header__actions-desktop">
              <PublicButton variant="secondary">Sign in</PublicButton>
              <PublicButton variant="primary">Book now</PublicButton>
            </div>

            <PublicIconButton
              label={menuOpen ? "Close menu" : "Open menu"}
              className="jp-v2-header__menu-btn"
              aria-expanded={menuOpen}
              aria-controls={menuId}
              onClick={() => setMenuOpen((open) => !open)}
            >
              <span aria-hidden="true">{menuOpen ? "✕" : "☰"}</span>
            </PublicIconButton>
          </div>
        </div>
      </PublicContainer>

      <nav
        id={menuId}
        className={["jp-v2-header__mobile-nav", menuOpen ? "jp-v2-header__mobile-nav--open" : ""].filter(Boolean).join(" ")}
        aria-label="Mobile navigation"
        hidden={!menuOpen}
      >
        <PublicContainer>
          <ul className="jp-v2-header__mobile-list">
            {NAV_LINKS.map((link) => (
              <li key={link.href}>
                <Link href={link.href} onClick={closeMenu}>
                  {link.label}
                </Link>
              </li>
            ))}
            <li className="jp-v2-header__mobile-cta">
              <PublicButton variant="secondary" block onClick={closeMenu}>
                Sign in
              </PublicButton>
              <PublicButton variant="primary" block onClick={closeMenu}>
                Book now
              </PublicButton>
            </li>
          </ul>
        </PublicContainer>
      </nav>
    </header>
  );
}
