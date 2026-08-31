"use client";

import { useCallback, useEffect, useMemo, useState } from "react";

export type DashboardTourStep = {
  id: string;
  title: string;
  body: string;
  target: string | null;
};

export type DashboardTourPayload = {
  tour_key: string;
  tours: Record<string, { status: string; at: string }>;
  steps: DashboardTourStep[];
  should_auto_start: boolean;
};

function resolveTarget(selector: string | null): HTMLElement | null {
  if (!selector) return null;
  try {
    return document.querySelector(`[data-tour-target="${CSS.escape(selector)}"]`);
  } catch {
    return document.querySelector(`[data-tour-target="${selector}"]`);
  }
}

function TourGuideCharacter() {
  return (
    <svg viewBox="0 0 96 96" role="img" aria-label="JetPakistan travel guide" className="jp-dash-tour-guide h-[72px] w-[72px] shrink-0">
      <defs>
        <style>{`
          .jp-dash-tour-guide .wave { transform-origin: 72px 48px; animation: jp-dash-tour-wave 2.4s ease-in-out infinite; }
          @media (prefers-reduced-motion: reduce) { .jp-dash-tour-guide .wave { animation: none; } }
          @keyframes jp-dash-tour-wave { 0%,100%{transform:rotate(0)} 50%{transform:rotate(12deg)} }
        `}</style>
      </defs>
      <circle cx="48" cy="48" r="46" fill="#0B3D2E" />
      <circle cx="48" cy="36" r="16" fill="#F5E6D3" />
      <rect x="28" y="52" width="40" height="28" rx="10" fill="#1F6B4A" />
      <circle cx="42" cy="34" r="2" fill="#0B3D2E" />
      <circle cx="54" cy="34" r="2" fill="#0B3D2E" />
      <path d="M42 40c2 2 10 2 12 0" stroke="#0B3D2E" strokeWidth="1.5" fill="none" strokeLinecap="round" />
      <path className="wave" d="M68 48c6-2 10 4 8 10" stroke="#C4A35A" strokeWidth="3" fill="none" strokeLinecap="round" />
    </svg>
  );
}

async function fetchTours(portal: "admin" | "staff"): Promise<DashboardTourPayload | null> {
  const response = await fetch(`/api/dashboard/tours?portal=${encodeURIComponent(portal)}`, {
    method: "GET",
    credentials: "same-origin",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  if (!response.ok) return null;
  const json = (await response.json()) as { data?: DashboardTourPayload };
  return json.data ?? null;
}

async function patchTour(
  portal: "admin" | "staff",
  body: { tour_key: string; status?: string; restart?: boolean },
): Promise<boolean> {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
  const response = await fetch(`/api/dashboard/tours?portal=${encodeURIComponent(portal)}`, {
    method: "PATCH",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrf,
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify({ ...body, portal }),
  });
  return response.ok;
}

export function DashboardTourHost({ portal }: { portal: "admin" | "staff" }) {
  const [payload, setPayload] = useState<DashboardTourPayload | null>(null);
  const [open, setOpen] = useState(false);
  const [index, setIndex] = useState(0);

  const load = useCallback(async () => {
    const data = await fetchTours(portal);
    if (!data) return;
    setPayload(data);
    if (data.should_auto_start && data.steps.length > 0) setOpen(true);
  }, [portal]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    const handler = () => {
      void (async () => {
        if (payload?.tour_key) {
          await patchTour(portal, { tour_key: payload.tour_key, restart: true });
        }
        const refreshed = await fetchTours(portal);
        if (refreshed) {
          setPayload(refreshed);
          setIndex(0);
          setOpen(true);
        }
      })();
    };
    window.addEventListener("jp-backoffice-tour-restart", handler);
    return () => window.removeEventListener("jp-backoffice-tour-restart", handler);
  }, [payload?.tour_key, portal]);

  const usableSteps = useMemo(() => {
    if (!payload) return [];
    return payload.steps.filter((step) => !step.target || resolveTarget(step.target) !== null);
  }, [payload, open]);

  useEffect(() => {
    if (!open) return;
    const step = usableSteps[index];
    if (!step?.target) return;
    const el = resolveTarget(step.target);
    el?.scrollIntoView({ block: "nearest", behavior: "smooth" });
    el?.setAttribute("data-tour-active", "true");
    return () => el?.removeAttribute("data-tour-active");
  }, [open, index, usableSteps]);

  if (!open || !payload || usableSteps.length === 0) return null;

  const step = usableSteps[Math.min(index, usableSteps.length - 1)];
  const total = usableSteps.length;
  const current = index + 1;
  const isLast = index >= total - 1;

  const finish = async (status: "completed" | "skipped") => {
    setOpen(false);
    await patchTour(portal, { tour_key: payload.tour_key, status });
    const refreshed = await fetchTours(portal);
    if (refreshed) setPayload(refreshed);
  };

  return (
    <div
      className="fixed inset-0 z-[80] flex items-end justify-center bg-black/35 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="backoffice-tour-title"
      data-testid="backoffice-dashboard-tour-overlay"
    >
      <div className="w-full max-w-md rounded-xl border border-jp-border bg-jp-surface p-5 shadow-lg">
        <div className="flex items-start gap-3">
          <TourGuideCharacter />
          <div className="min-w-0 flex-1">
            <p className="text-xs font-semibold uppercase tracking-wide text-jp-muted" data-testid="backoffice-tour-progress">
              {current} of {total}
            </p>
            <h2 id="backoffice-tour-title" className="mt-1 text-lg font-semibold text-jp-text">
              {step.title}
            </h2>
            <p className="mt-2 text-sm text-jp-muted">{step.body}</p>
          </div>
        </div>
        <div className="mt-5 flex flex-wrap items-center justify-between gap-2">
          <button
            type="button"
            className="rounded-md px-3 py-2 text-sm text-jp-muted hover:text-jp-text"
            onClick={() => void finish("skipped")}
            data-testid="backoffice-tour-skip"
          >
            Skip
          </button>
          <div className="flex gap-2">
            <button
              type="button"
              className="rounded-md border border-jp-border px-3 py-2 text-sm disabled:opacity-40"
              disabled={index === 0}
              onClick={() => setIndex((value) => Math.max(0, value - 1))}
            >
              Previous
            </button>
            {isLast ? (
              <button
                type="button"
                className="rounded-md bg-jp-accent px-3 py-2 text-sm font-semibold text-white"
                onClick={() => void finish("completed")}
                data-testid="backoffice-tour-finish"
              >
                Finish
              </button>
            ) : (
              <button
                type="button"
                className="rounded-md bg-jp-accent px-3 py-2 text-sm font-semibold text-white"
                onClick={() => setIndex((value) => Math.min(total - 1, value + 1))}
              >
                Next
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
