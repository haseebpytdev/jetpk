"use client";

import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BlueprintFieldSegmentProps = {
  children: ReactNode;
  className?: string;
  widthClass?: string;
};

/** Interior segment inside the canonical blueprint search row (divider-separated, no outer card). */
export function BlueprintFieldSegment({ children, className, widthClass }: BlueprintFieldSegmentProps) {
  return (
    <div className={cn("min-w-0 px-3 py-2", widthClass, className)}>
      {children}
    </div>
  );
}

export function BlueprintSearchRow({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        "hidden w-full min-w-0 lg:flex lg:min-h-[4.5rem] lg:items-stretch lg:divide-x lg:divide-jp-border",
        className,
      )}
      data-testid="blueprint-search-row-desktop"
    >
      {children}
    </div>
  );
}
