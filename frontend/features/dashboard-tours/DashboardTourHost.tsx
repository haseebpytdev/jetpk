"use client";

import { useCallback, useEffect, useState } from "react";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { DashboardTourOverlay } from "./DashboardTourOverlay";
import type { TourPayload, TourStatus } from "./types";

type PortalKind = "customer" | "agent";

type DashboardTourHostProps = {
  portal: PortalKind;
};

function endpoint(portal: PortalKind): string {
  return portal === "customer" ? "/customer/dashboard-tours" : "/agent/dashboard-tours";
}

async function fetchTour(portal: PortalKind): Promise<TourPayload | null> {
  const result = await laravelRequest<TourPayload>(`${endpoint(portal)}?format=json`, {
    method: "GET",
    retryOnNetworkError: true,
  });
  if (!result.ok) return null;
  return result.data;
}

async function patchTour(
  portal: PortalKind,
  body: { tour_key: string; status?: TourStatus; restart?: boolean },
): Promise<boolean> {
  const result = await laravelRequest(`${endpoint(portal)}?format=json`, {
    method: "PATCH",
    json: body,
    retryCsrfOnce: true,
  });
  return result.ok;
}

export function DashboardTourHost({ portal }: DashboardTourHostProps) {
  const [payload, setPayload] = useState<TourPayload | null>(null);
  const [open, setOpen] = useState(false);

  const load = useCallback(async () => {
    const data = await fetchTour(portal);
    if (!data) return;
    setPayload(data);
    if (data.should_auto_start && data.steps.length > 0) {
      setOpen(true);
    }
  }, [portal]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    const handler = () => {
      void (async () => {
        if (!payload?.tour_key) {
          await load();
          setOpen(true);
          return;
        }
        await patchTour(portal, { tour_key: payload.tour_key, restart: true });
        const refreshed = await fetchTour(portal);
        if (refreshed) {
          setPayload(refreshed);
          setOpen(true);
        }
      })();
    };
    window.addEventListener("jp-dashboard-tour-restart", handler);
    return () => window.removeEventListener("jp-dashboard-tour-restart", handler);
  }, [load, payload?.tour_key, portal]);

  const onClose = async (status: TourStatus) => {
    setOpen(false);
    if (!payload?.tour_key) return;
    await patchTour(portal, { tour_key: payload.tour_key, status });
    const refreshed = await fetchTour(portal);
    if (refreshed) setPayload(refreshed);
  };

  if (!payload) return null;

  return (
    <DashboardTourOverlay
      steps={payload.steps}
      open={open}
      onClose={(result) => {
        void onClose(result);
      }}
      testIdPrefix={`${portal}-dashboard-tour`}
    />
  );
}

export function requestDashboardTourRestart(): void {
  window.dispatchEvent(new Event("jp-dashboard-tour-restart"));
}
