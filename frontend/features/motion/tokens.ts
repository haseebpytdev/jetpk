export const MOTION_TOKENS = {
  instant: "var(--jp-motion-instant)",
  fast: "var(--jp-motion-fast)",
  standard: "var(--jp-motion-standard)",
  emphasized: "var(--jp-motion-emphasized)",
  route: "var(--jp-motion-route)",
  easeStandard: "var(--jp-ease-standard)",
  easeEmphasized: "var(--jp-ease-emphasized)",
  easeDecelerate: "var(--jp-ease-decelerate)",
} as const;

export const MOTION_DURATIONS_MS = {
  instant: 100,
  fast: 160,
  standard: 230,
  emphasized: 340,
  route: 240,
} as const;
