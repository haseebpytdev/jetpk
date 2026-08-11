"use client";

import type { ReactNode } from "react";

/** Live settings use Next + DashboardSettings* APIs — no Laravel Blade handoff. */
export function SettingsLiveGate({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
