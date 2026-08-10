"use client";

import type { ReactNode } from "react";
import { LaravelLiveRedirect } from "@/components/dashboard/laravel-live-redirect";
import { EmptyState } from "@/components/ui/empty-state";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

/** Live mode must not expose fixture preview settings editors in production. */
export function SettingsLiveGate({ children }: { children: ReactNode }) {
  const isLive = useDashboardLiveMode();
  const portal = useDashboardPortal();

  if (!isLive) {
    return children;
  }

  if (portal === "admin") {
    return <LaravelLiveRedirect route="admin.settings.index" label="Settings" />;
  }

  return (
    <PageContainer>
      <PageHeader
        title="Settings"
        description="Platform settings are managed in the Laravel admin console."
      />
      <EmptyState
        title="Settings unavailable here"
        description="Staff settings are not exposed through the live dashboard preview shell."
      />
    </PageContainer>
  );
}
