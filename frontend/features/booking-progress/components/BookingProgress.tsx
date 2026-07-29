"use client";

import Link from "next/link";
import { cn } from "@/lib/cn";
import type { BookingProgressStep } from "../types";

type BookingProgressProps = {
  steps: BookingProgressStep[];
  ariaLabel?: string;
  compact?: boolean;
  className?: string;
};

export function BookingProgress({
  steps,
  ariaLabel = "Booking progress",
  compact = false,
  className,
}: BookingProgressProps) {
  return (
    <nav aria-label={ariaLabel} className={cn("w-full", className)}>
      <ol
        className={cn(
          "flex flex-wrap items-center gap-2",
          compact ? "text-jp-xs" : "text-jp-sm",
        )}
      >
        {steps.map((step, index) => {
          const content = (
            <span
              className={cn(
                "inline-flex items-center gap-1 rounded-jp-pill border px-2.5 py-1",
                step.state === "current" && "border-jp-primary bg-jp-primary-soft font-semibold text-jp-text",
                step.state === "completed" && "border-jp-border bg-jp-surface text-jp-text",
                step.state === "upcoming" && "border-jp-border bg-jp-surface-muted text-jp-muted",
                step.state === "skipped" && "border-jp-border bg-jp-surface-muted text-jp-muted italic",
              )}
            >
              <span
                aria-hidden="true"
                className={cn(
                  "inline-flex h-5 w-5 items-center justify-center rounded-full text-[0.65rem] font-bold",
                  step.state === "current" && "bg-jp-primary text-white",
                  step.state === "completed" && "bg-jp-primary/15 text-jp-primary",
                  step.state === "upcoming" && "bg-jp-surface-muted text-jp-muted",
                )}
              >
                {step.state === "completed" ? "✓" : index + 1}
              </span>
              <span>{step.label}</span>
            </span>
          );

          return (
            <li key={step.key} aria-current={step.state === "current" ? "step" : undefined} className="flex items-center gap-2">
              {index > 0 ? <span aria-hidden="true" className="text-jp-muted">→</span> : null}
              {step.href && step.state === "completed" ? (
                <Link href={step.href} className="focus-visible:outline-none focus-visible:shadow-jp-focus rounded-jp-pill">
                  {content}
                </Link>
              ) : (
                content
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
