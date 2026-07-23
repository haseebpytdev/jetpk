export type DashboardMode = "preview" | "live";

export function getDashboardMode(): DashboardMode {
  const mode = process.env.NEXT_PUBLIC_DASHBOARD_MODE;
  return mode === "live" ? "live" : "preview";
}

export function useMockData(): boolean {
  if (process.env.NEXT_PUBLIC_USE_MOCK_DATA === "false") {
    return false;
  }
  return getDashboardMode() === "preview" || process.env.NEXT_PUBLIC_USE_MOCK_DATA === "true";
}

export function mutationsAllowed(): boolean {
  if (getDashboardMode() === "preview") {
    return process.env.NEXT_PUBLIC_ALLOW_MUTATIONS === "true";
  }
  return process.env.NEXT_PUBLIC_ALLOW_MUTATIONS !== "false";
}

export function assertPreviewSafe(action: string): void {
  if (mutationsAllowed()) {
    return;
  }
  throw new Error(`Preview mode: ${action} is disabled (NEXT_PUBLIC_ALLOW_MUTATIONS=false).`);
}
