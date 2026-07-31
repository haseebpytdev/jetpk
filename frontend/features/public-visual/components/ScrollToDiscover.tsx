"use client";

import { cn } from "@/lib/cn";

type ScrollToDiscoverProps = {
  targetId?: string;
  className?: string;
};

/** Blueprint homepage affordance below the hero/search overlap. */
export function ScrollToDiscover({ targetId = "homepage-routes", className }: ScrollToDiscoverProps) {
  return (
    <div className={cn("flex justify-center py-jp-lg", className)} data-testid="scroll-to-discover">
      <a
        href={`#${targetId}`}
        className="inline-flex flex-col items-center gap-2 text-jp-sm font-semibold text-jp-muted transition-colors hover:text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
      >
        <span>Scroll to Discover</span>
        <span className="flex h-9 w-9 items-center justify-center rounded-full border border-jp-border bg-jp-surface shadow-jp-sm" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M8 3v10M4 9l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      </a>
    </div>
  );
}
