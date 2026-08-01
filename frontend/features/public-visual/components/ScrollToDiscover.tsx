"use client";

import { cn } from "@/lib/cn";

type ScrollToDiscoverProps = {
  targetId?: string;
  className?: string;
};

/** Blueprint homepage affordance below the hero/search overlap. */
export function ScrollToDiscover({ targetId = "homepage-routes", className }: ScrollToDiscoverProps) {
  return (
    <div
      className={cn("relative pb-3 pt-6", className)}
      data-testid="scroll-to-discover"
    >
      <div className="pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 px-4">
        <svg viewBox="0 0 960 32" className="mx-auto h-8 w-full max-w-[960px]" fill="none" aria-hidden="true">
          <path
            d="M8 16 C 160 4, 280 28, 480 16 S 720 4, 952 16"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeDasharray="3 5"
            className="text-jp-primary/35"
          />
          <circle cx="8" cy="16" r="3.5" className="fill-jp-primary" />
          <path d="M948 13 L956 16 L948 19 Z" className="fill-jp-primary" />
        </svg>
      </div>

      <div className="relative flex justify-center">
        <a
          href={`#${targetId}`}
          className="inline-flex flex-col items-center gap-1 text-jp-xs font-semibold text-jp-muted transition-colors hover:text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          <span>Scroll to Discover</span>
          <span
            className="flex h-7 w-7 items-center justify-center rounded-full border border-jp-border bg-jp-surface shadow-jp-sm"
            aria-hidden="true"
          >
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <path
                d="M8 3v10M4 9l4 4 4-4"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </span>
        </a>
      </div>
    </div>
  );
}
