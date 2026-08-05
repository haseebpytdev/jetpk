"use client";

import { useSearchParams } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

/**
 * JP-OPS-06 execution contract: live production build enables mutation UI only when
 * no `dataSourcePreview` query param is simulating read-only preview states.
 */
export function useRuntimeLiveMutationsEnabled(): boolean {
  const isLiveBuild = useDashboardLiveMode();
  const searchParams = useSearchParams();
  if (!isLiveBuild) {
    return false;
  }
  return !searchParams.get("dataSourcePreview");
}
