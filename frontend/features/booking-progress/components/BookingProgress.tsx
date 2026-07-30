"use client";

import Link from "next/link";
import { cn } from "@/lib/cn";
import type { BookingProgressStep } from "../types";
import { visibleProgressSteps } from "@/features/booking-layout/constants/journey-steps";

type BookingProgressProps = {
  steps: BookingProgressStep[];
  ariaLabel?: string;
  compact?: boolean;
  className?: string;
};

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
        isCurrent && "bg-jp-primary text-white ring-2 ring-jp-primary ring-offset-2 ring-offset-jp-bg",
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

  const label = (
    <span
      className={cn(
        "text-center leading-tight",
        compact ? "max-w-[4.5rem] text-[0.65rem]" : "max-w-[5.5rem] text-jp-xs",
        isCurrent && "font-semibold text-jp-text",
        isComplete && "text-jp-text",
        isUpcoming && "text-jp-muted",
      )}
    >
      {step.label}
    </span>
  );

  const content = (
    <span className="flex flex-col items-center gap-1">
      {circle}
      {!compact ? label : <span className="sr-only">{step.label}</span>}
    </span>
  );

  if (step.href && isComplete) {
    return (
      <Link
        href={step.href}
        className="rounded-jp-md focus-visible:outline-none focus-visible:shadow-jp-focus"
        aria-label={`${step.label}, completed`}
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
                      "h-0.5 flex-1",
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
                      "h-0.5 flex-1",
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
                    "mt-1 hidden text-center text-jp-xs sm:block",
                    step.state === "current" && "font-semibold text-jp-text",
                    step.state === "completed" && "text-jp-text",
                    step.state === "upcoming" && "text-jp-muted",
                  )}
                >
                  {step.label}
                </span>
              ) : null}
            </div>
          </li>
        ))}
      </ol>
    </nav>
  );
}
