"use client";

import { useEffect } from "react";
import { laravelRouteUrl } from "@/lib/laravel-route-url";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

type Props = {
  route: string;
  params?: Record<string, string | undefined | null>;
  label?: string;
};

/** In live mode, hand off to the mature Laravel module instead of a preview Next page. */
export function LaravelLiveRedirect({ route, params, label }: Props) {
  const isLive = useDashboardLiveMode();

  useEffect(() => {
    if (!isLive) {
      return;
    }
    window.location.assign(laravelRouteUrl(route, params));
  }, [isLive, route, params]);

  if (!isLive) {
    return null;
  }

  return (
    <p className="text-sm text-jp-muted" data-testid="laravel-live-redirect">
      Opening {label ?? route}…
    </p>
  );
}
