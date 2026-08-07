/**
 * JetPakistan cross-surface typography contract (JETPK-UI-02).
 *
 * Mirrors frontend/lib/theme/typography.ts — keep families in sync.
 * Dashboard preserves denser operational sizing via dashboard/styles/typography-tokens.css.
 */
export const JETPK_FONT_BODY_FAMILY = "Inter";

export const JETPK_FONT_DISPLAY_FAMILY = "Space Grotesk";

export const JETPK_FONT_CSS_VARS = {
  body: "--font-body",
  display: "--font-display",
  sans: "--jp-font-sans",
  brandDisplay: "--jp-font-display",
  mono: "--jp-font-mono",
} as const;

export const JETPK_TYPOGRAPHY_CONTRACT = {
  brandDisplay: {
    family: JETPK_FONT_DISPLAY_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.display,
    tailwind: "font-display",
    usage: "Page titles, KPI values, sidebar brand",
  },
  brandUi: {
    family: JETPK_FONT_BODY_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.body,
    tailwind: "font-sans",
    usage: "Navigation, body, controls, tables, forms, metadata",
  },
} as const;

export type JetpkTypographyRole = keyof typeof JETPK_TYPOGRAPHY_CONTRACT;

/** Tailwind fontFamily stacks — see frontend/lib/theme/typography.ts */
export const JETPK_TAILWIND_FONT_SANS = [
  "var(--font-body)",
  "system-ui",
  "-apple-system",
  '"Segoe UI"',
  "sans-serif",
];

export const JETPK_TAILWIND_FONT_DISPLAY = [
  "var(--font-display)",
  "var(--font-body)",
  "system-ui",
  "-apple-system",
  '"Segoe UI"',
  "sans-serif",
];

export const JETPK_TAILWIND_FONT_MONO = [
  "ui-monospace",
  '"Cascadia Code"',
  '"Segoe UI Mono"',
  "monospace",
];
