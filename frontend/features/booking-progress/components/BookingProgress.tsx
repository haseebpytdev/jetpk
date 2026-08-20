"use client";

import Link from "next/link";
import { cn } from "@/lib/cn";
import type { BookingProgressStep } from "../types";
import { visibleProgressSteps, BOOKING_JOURNEY_STEP_LABELS } from "@/features/booking-layout/constants/journey-steps";

type BookingProgressProps = {
  steps: BookingProgressStep[];
  ariaLabel?: string;
  /** When true, hides visible labels (mobile-only). Desktop should use full connected labels. */
  compact?: boolean;
  className?: string;
};

function resolveStepLabel(step: BookingProgressStep): string {
  return BOOKING_JOURNEY_STEP_LABELS[step.key] ?? step.label;
}

function StepIndicator({
  step,
  displayNumber,
  compact,
}: {
  step: BookingProgressStep;
  displayNumber: number;
  compact: boolean;
}) {
  const isComplete = step.state === "completed";
  const isCurrent = step.state === "current";
  const isUpcoming = step.state === "upcoming";

  const circle = (
    <span
      aria-hidden="true"
      className={cn(
        "inline-flex items-center justify-center rounded-full font-bold transition-colors motion-reduce:transition-none",
        compact ? "h-6 w-6 text-[0.65rem]" : "h-8 w-8 text-jp-xs",
        isComplete && "bg-jp-primary text-white",
        isCurrent && "scale-110 bg-jp-primary text-white shadow-md ring-2 ring-jp-primary/30 ring-offset-2 ring-offset-jp-bg",
        isUpcoming && "border border-jp-border bg-jp-surface-muted text-jp-muted",
      )}
    >
      {isComplete ? (
        <svg viewBox="0 0 16 16" className={compact ? "h-3 w-3" : "h-4 w-4"} fill="currentColor" aria-hidden="true">
          <path d="M6.5 11.5 3.5 8.5l1-1 2 2 5-5 1 1-6 6z" />
        </svg>
      ) : (
        displayNumber
      )}
    </span>
  );

  const content = (
    <span className="flex flex-col items-center gap-1">
      {circle}
      {compact ? <span className="sr-only">{resolveStepLabel(step)}</span> : null}
    </span>
  );

  if (step.href && isComplete) {
    return (
      <Link
        href={step.href}
        className="rounded-jp-md focus-visible:outline-none focus-visible:shadow-jp-focus"
        aria-label={`${resolveStepLabel(step)}, completed`}
      >
        {content}
      </Link>
    );
  }

  return (
    <span aria-current={isCurrent ? "step" : undefined}>
      {content}
      {compact ? null : (
        <span className="sr-only">
          {isCurrent ? ", current step" : isComplete ? ", completed" : ", upcoming"}
        </span>
      )}
    </span>
  );
}

export function BookingProgress({
  steps,
  ariaLabel = "Booking progress",
  compact = false,
  className,
}: BookingProgressProps) {
  const visible = visibleProgressSteps(steps);

  return (
    <nav aria-label={ariaLabel} className={cn("w-full", className)} data-testid="booking-progress">
      <ol
        className={cn(
          "flex items-start justify-between gap-1",
          compact && "overflow-x-auto pb-1",
        )}
      >
        {visible.map((step, index) => (
          <li key={step.key} className="flex min-w-0 flex-1 items-start">
            <div className="flex w-full flex-col items-center">
              <div className="flex w-full items-center">
                {index > 0 ? (
                  <span
                    aria-hidden="true"
                    className={cn(
                      "jp-progress-fill h-0.5 flex-1",
                      step.state === "upcoming" ? "bg-jp-border" : "bg-jp-primary",
                    )}
                  />
                ) : (
                  <span className="flex-1" aria-hidden="true" />
                )}
                <StepIndicator step={step} displayNumber={index + 1} compact={compact} />
                {index < visible.length - 1 ? (
                  <span
                    aria-hidden="true"
                    className={cn(
                      "jp-progress-fill h-0.5 flex-1",
                      visible[index + 1]?.state === "upcoming" ? "bg-jp-border" : "bg-jp-primary",
                    )}
                  />
                ) : (
                  <span className="flex-1" aria-hidden="true" />
                )}
              </div>
              {!compact ? (
                <span
                  className={cn(
                    "mt-1 text-center text-jp-xs",
                    compact ? "sr-only" : "hidden min-[480px]:block",
                    step.state === "current" && "font-bold text-jp-primary",
                    step.state === "completed" && "text-jp-text",
                    step.state === "upcoming" && "text-jp-muted",
                  )}
                >
                  {resolveStepLabel(step)}
                </span>
              ) : null}
            </div>
          </li>
        ))}
      </ol>
    </nav>
  );
}
