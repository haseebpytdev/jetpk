/**
 * JetPakistan global typography authority (JP-DASH-03).
 *
 * UI/display: Inter — all normal JetPakistan text including headings and brand marks.
 * Numeric/mono: IBM Plex Mono — fares, codes, KPI machine values.
 */
export const JETPK_LEGACY_FONT_BODY_FAMILY = "Inter";

export const JETPK_LEGACY_FONT_DISPLAY_FAMILY = "Inter";

export const JETPK_LEGACY_FONT_MONO_FAMILY = "IBM Plex Mono";

export const JETPK_FONT_CSS_VARS = {
  body: "--font-body",
  display: "--font-display",
  mono: "--font-mono",
  jetpkUi: "--font-jetpk-ui",
  jetpkDisplay: "--font-jetpk-display",
  jetpk: "--font-jetpk",
  jetpkMono: "--font-jetpk-mono",
  sans: "--jp-font-sans",
  brandDisplay: "--jp-font-display",
  jpMono: "--jp-font-mono",
} as const;

export const JETPK_TYPOGRAPHY_CONTRACT = {
  brandDisplay: {
    family: JETPK_LEGACY_FONT_DISPLAY_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.display,
    tailwind: "font-display",
    usage: "Hero/display headings, major page titles, KPI emphasis",
  },
  brandUi: {
    family: JETPK_LEGACY_FONT_BODY_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.body,
    tailwind: "font-sans",
    usage: "Navigation, body, controls, tables, forms, metadata",
  },
  brandMono: {
    family: JETPK_LEGACY_FONT_MONO_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.mono,
    tailwind: "font-mono",
    usage: "Fares, references, uppercase labels, numeric tables",
  },
} as const;

export type JetpkTypographyRole = keyof typeof JETPK_TYPOGRAPHY_CONTRACT;

/** Tailwind stacks — each entry is a separate family; never wrap comma stacks in one var(). */
export const JETPK_TAILWIND_FONT_SANS = [
  "var(--font-body)",
  "system-ui",
  "-apple-system",
  '"Segoe UI"',
  "sans-serif",
];

export const JETPK_TAILWIND_FONT_DISPLAY = [
  "var(--font-body)",
  "system-ui",
  "-apple-system",
  '"Segoe UI"',
  "sans-serif",
];

export const JETPK_TAILWIND_FONT_MONO = [
  "var(--font-mono)",
  "ui-monospace",
  '"Cascadia Code"',
  '"Segoe UI Mono"',
  "monospace",
];
