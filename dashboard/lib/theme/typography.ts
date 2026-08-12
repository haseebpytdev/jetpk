/**
 * JetPakistan platform typography (OWNER-UAT W2-22). Admin/Staff = Plus Jakarta only.
 */
export const JETPK_FONT_BODY_FAMILY = "Plus Jakarta Sans";
export const JETPK_FONT_DISPLAY_FAMILY = "Plus Jakarta Sans";
export const JETPK_FONT_MONO_FAMILY = "IBM Plex Mono";
export const JETPK_LEGACY_FONT_BODY_FAMILY = JETPK_FONT_BODY_FAMILY;
export const JETPK_LEGACY_FONT_DISPLAY_FAMILY = JETPK_FONT_DISPLAY_FAMILY;
export const JETPK_LEGACY_FONT_MONO_FAMILY = JETPK_FONT_MONO_FAMILY;

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
    family: JETPK_FONT_DISPLAY_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.display,
    tailwind: "font-display",
    usage: "Operational page titles, KPI emphasis",
  },
  brandUi: {
    family: JETPK_FONT_BODY_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.body,
    tailwind: "font-sans",
    usage: "Navigation, body, controls, tables, forms, metadata",
  },
  brandMono: {
    family: JETPK_FONT_MONO_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.mono,
    tailwind: "font-mono",
    usage: "PNR, booking references, true machine identifiers",
  },
} as const;

export type JetpkTypographyRole = keyof typeof JETPK_TYPOGRAPHY_CONTRACT;

export const JETPK_TAILWIND_FONT_SANS = ["var(--font-body)", "system-ui", "-apple-system", '"Segoe UI"', "sans-serif"];
export const JETPK_TAILWIND_FONT_DISPLAY = ["var(--font-display)", "var(--font-body)", "system-ui", "-apple-system", '"Segoe UI"', "sans-serif"];
export const JETPK_TAILWIND_FONT_MONO = ["var(--font-mono)", "ui-monospace", '"Cascadia Code"', '"Segoe UI Mono"', "monospace"];
