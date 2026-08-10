"use client";

import { PreviewDataBanner } from "@/components/ui/page-layout";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

/** Preview-only synthetic data notice; hidden in live production dashboard mode. */
export function DetailDrawerSourceNotice({ className }: { className?: string }) {
  const isLive = useDashboardLiveMode();

  if (isLive) {
    return null;
  }

  return <PreviewDataBanner className={className} />;
}
