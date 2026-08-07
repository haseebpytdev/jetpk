import type { Config } from "tailwindcss";
import {
  JETPK_TAILWIND_FONT_DISPLAY,
  JETPK_TAILWIND_FONT_MONO,
  JETPK_TAILWIND_FONT_SANS,
} from "./lib/theme/typography";

const config: Config = {
  content: [
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./features/**/*.{js,ts,jsx,tsx,mdx}",
    "./layouts/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        jp: {
          sidebar: "#111827",
          accent: "#10B981",
          "accent-muted": "#059669",
          surface: "#F9FAFB",
          card: "#FFFFFF",
          border: "#E5E7EB",
          muted: "#6B7280",
        },
      },
      fontFamily: {
        sans: JETPK_TAILWIND_FONT_SANS,
        display: JETPK_TAILWIND_FONT_DISPLAY,
        mono: JETPK_TAILWIND_FONT_MONO,
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
