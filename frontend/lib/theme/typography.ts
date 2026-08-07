/**
 * JetPakistan cross-surface typography contract (JETPK-UI-02).
 *
 * Brand display: Space Grotesk — hero, major marketing headings, selected page titles.
 * Brand UI/body: Inter — navigation, body copy, controls, tables, forms, metadata.
 *
 * Both frontend and dashboard consume the same semantic families via CSS variables
 * defined in each app's token stylesheet and bound in the root layout.
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
    usage: "Hero/display headings, major page titles, KPI emphasis",
  },
  brandUi: {
    family: JETPK_FONT_BODY_FAMILY,
    cssVar: JETPK_FONT_CSS_VARS.body,
    tailwind: "font-sans",
    usage: "Navigation, body, controls, tables, forms, metadata",
  },
} as const;

export type JetpkTypographyRole = keyof typeof JETPK_TYPOGRAPHY_CONTRACT;

/**
 * Tailwind fontFamily stacks — each entry is a separate family name.
 * Do not pass a single var() whose value contains commas; browsers treat that as one invalid name.
 */
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
