import { getDashboardMode } from "@/lib/preview";

/**
 * Build-time live mode flag — NEXT_PUBLIC_DASHBOARD_MODE is inlined at compile time.
 * Must not defer to mount: SSR and first client render must agree.
 */
export function useDashboardLiveMode(): boolean {
  return getDashboardMode() === "live";
}
