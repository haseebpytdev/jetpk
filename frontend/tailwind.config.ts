import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./features/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        jp: {
          primary: "var(--jp-primary)",
          "primary-hover": "var(--jp-primary-hover)",
          "primary-active": "var(--jp-primary-active)",
          "primary-soft": "var(--jp-primary-soft)",
          "primary-border": "var(--jp-primary-border)",
          accent: "var(--jp-accent)",
          page: "var(--jp-page-bg)",
          surface: "var(--jp-surface)",
          "surface-muted": "var(--jp-surface-muted)",
          text: "var(--jp-text)",
          muted: "var(--jp-text-muted)",
          border: "var(--jp-border)",
          footer: "var(--jp-footer-bg)",
        },
      },
      fontFamily: {
        sans: ["var(--font-body)", "system-ui", "sans-serif"],
        display: ["var(--font-display)", "system-ui", "sans-serif"],
      },
      fontSize: {
        "jp-xs": "var(--jp-fs-xs)",
        "jp-sm": "var(--jp-fs-sm)",
        "jp-body": "var(--jp-fs-body)",
        "jp-md": "var(--jp-fs-md)",
        "jp-lg": "var(--jp-fs-lg)",
        "jp-xl": "var(--jp-fs-xl)",
        "jp-h3": "var(--jp-fs-h3)",
        "jp-h2": "var(--jp-fs-h2)",
        "jp-h1": "var(--jp-fs-h1)",
      },
      spacing: {
        "jp-2xs": "var(--jp-space-2xs)",
        "jp-xs": "var(--jp-space-xs)",
        "jp-sm": "var(--jp-space-sm)",
        "jp-md": "var(--jp-space-md)",
        "jp-lg": "var(--jp-space-lg)",
        "jp-xl": "var(--jp-space-xl)",
        "jp-2xl": "var(--jp-space-2xl)",
        "jp-3xl": "var(--jp-space-3xl)",
        "jp-4xl": "var(--jp-space-4xl)",
        "jp-5xl": "clamp(63px, calc(3.9375rem + 0.078125vw), 65px)",
      },
      borderRadius: {
        "jp-sm": "var(--jp-radius-sm)",
        "jp-md": "var(--jp-radius-md)",
        "jp-lg": "var(--jp-radius-lg)",
        "jp-xl": "var(--jp-radius-xl)",
        "jp-pill": "var(--jp-radius-pill)",
        "jp-button": "var(--jp-button-radius)",
        "jp-card": "var(--jp-card-radius)",
      },
      boxShadow: {
        "jp-sm": "var(--jp-shadow-sm)",
        "jp-md": "var(--jp-shadow-md)",
        "jp-card": "var(--jp-shadow-card)",
        "jp-focus": "var(--jp-focus-ring)",
      },
      maxWidth: {
        "jp-container": "var(--jp-maxw)",
      },
      height: {
        "jp-nav": "var(--jp-nav-height)",
        "jp-button": "var(--jp-button-height)",
      },
      minHeight: {
        "jp-button": "var(--jp-button-height)",
        "jp-tap": "44px",
      },
      transitionDuration: {
        ui: "150ms",
        drawer: "200ms",
      },
    },
  },
  plugins: [],
};

export default config;
