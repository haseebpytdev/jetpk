"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { TourGuideCharacter } from "./TourGuideCharacter";
import type { TourStep } from "./types";

type DashboardTourOverlayProps = {
  steps: TourStep[];
  open: boolean;
  onClose: (result: "completed" | "skipped") => void;
  testIdPrefix?: string;
};

function resolveTarget(selector: string | null): HTMLElement | null {
  if (!selector) return null;
  try {
    return document.querySelector(`[data-tour-target="${CSS.escape(selector)}"]`);
  } catch {
    return document.querySelector(`[data-tour-target="${selector}"]`);
  }
}

export function DashboardTourOverlay({
  steps,
  open,
  onClose,
  testIdPrefix = "dashboard-tour",
}: DashboardTourOverlayProps) {
  const usableSteps = useMemo(() => {
    return steps.filter((step) => {
      if (!step.target) return true;
      return resolveTarget(step.target) !== null;
    });
  }, [steps, open]);

  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (open) setIndex(0);
  }, [open, steps]);

  useEffect(() => {
    if (!open) return;
    const step = usableSteps[index];
    if (!step?.target) return;
    const el = resolveTarget(step.target);
    el?.scrollIntoView({ block: "nearest", behavior: "smooth" });
    el?.setAttribute("data-tour-active", "true");
    return () => {
      el?.removeAttribute("data-tour-active");
    };
  }, [open, index, usableSteps]);

  const finish = useCallback(
    (result: "completed" | "skipped") => {
      onClose(result);
    },
    [onClose],
  );

  if (!open || usableSteps.length === 0) return null;

  const step = usableSteps[Math.min(index, usableSteps.length - 1)];
  const total = usableSteps.length;
  const current = index + 1;
  const isLast = index >= total - 1;

  return (
    <div
      className="fixed inset-0 z-[80] flex items-end justify-center bg-black/35 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby={`${testIdPrefix}-title`}
      data-testid={`${testIdPrefix}-overlay`}
    >
      <div className="w-full max-w-md rounded-jp-lg border border-jp-border bg-jp-surface p-5 shadow-jp-md">
        <div className="flex items-start gap-3">
          <TourGuideCharacter className="shrink-0" />
          <div className="min-w-0 flex-1">
            <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted" data-testid={`${testIdPrefix}-progress`}>
              {current} of {total}
            </p>
            <h2 id={`${testIdPrefix}-title`} className="mt-1 font-sans text-jp-h3 font-semibold text-jp-text">
              {step.title}
            </h2>
            <p className="mt-2 text-jp-sm text-jp-muted">{step.body}</p>
          </div>
        </div>
        <div className="mt-5 flex flex-wrap items-center justify-between gap-2">
          <button
            type="button"
            className="rounded-jp-md px-3 py-2 text-jp-sm text-jp-muted hover:text-jp-text focus-visible:shadow-jp-focus"
            onClick={() => finish("skipped")}
            data-testid={`${testIdPrefix}-skip`}
          >
            Skip
          </button>
          <div className="flex gap-2">
            <button
              type="button"
              className="rounded-jp-md border border-jp-border px-3 py-2 text-jp-sm text-jp-text disabled:opacity-40 focus-visible:shadow-jp-focus"
              disabled={index === 0}
              onClick={() => setIndex((value) => Math.max(0, value - 1))}
              data-testid={`${testIdPrefix}-prev`}
            >
              Previous
            </button>
            {isLast ? (
              <button
                type="button"
                className="rounded-jp-md bg-jp-brand px-3 py-2 text-jp-sm font-semibold text-white focus-visible:shadow-jp-focus"
                onClick={() => finish("completed")}
                data-testid={`${testIdPrefix}-finish`}
              >
                Finish
              </button>
            ) : (
              <button
                type="button"
                className="rounded-jp-md bg-jp-brand px-3 py-2 text-jp-sm font-semibold text-white focus-visible:shadow-jp-focus"
                onClick={() => setIndex((value) => Math.min(total - 1, value + 1))}
                data-testid={`${testIdPrefix}-next`}
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
