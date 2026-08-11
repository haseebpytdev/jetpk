"use client";

import { useEffect, type ReactNode } from "react";
import { laravelRouteUrl } from "@/lib/laravel-route-url";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

type Props = {
  route: string;
  params?: Record<string, string | undefined | null>;
  label?: string;
  children?: ReactNode;
};

/** In live mode, hand off to the mature Laravel module instead of fixture preview UI. */
export function LaravelLiveRedirect({ route, params, label, children }: Props) {
  const isLive = useDashboardLiveMode();

  useEffect(() => {
    if (!isLive) {
      return;
    }
    window.location.assign(laravelRouteUrl(route, params));
  }, [isLive, route, params]);

  if (isLive) {
    return (
      <p className="text-sm text-jp-muted" data-testid="laravel-live-redirect">
        Opening {label ?? route}…
      </p>
    );
  }

  return <>{children ?? null}</>;
}
