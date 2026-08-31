"use client";

import { cn } from "@/lib/cn";
import { useEffect, useState, type ReactNode } from "react";

type MobileOrderSummaryProps = {
  children: ReactNode;
  label?: string;
  defaultExpanded?: boolean;
  className?: string;
};

export function MobileOrderSummary({
  children,
  label = "Order summary",
  defaultExpanded = false,
  className,
}: MobileOrderSummaryProps) {
  const [expanded, setExpanded] = useState(defaultExpanded);

  return (
    <div className={cn("lg:hidden", className)} data-testid="mobile-order-summary">
      <button
        type="button"
        className="flex w-full items-center justify-between rounded-jp-lg border border-jp-border bg-jp-surface px-4 py-3 text-left text-jp-sm font-semibold text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus"
        aria-expanded={expanded}
        onClick={() => setExpanded((value) => !value)}
      >
        {label}
        <span aria-hidden="true" className="text-jp-muted">
          {expanded ? "▲" : "▼"}
        </span>
      </button>
      {expanded ? <div className="mt-2">{children}</div> : null}
    </div>
  );
}

type MobileStickyActionProps = {
  children: ReactNode;
  className?: string;
};

export function MobileStickyAction({ children, className }: MobileStickyActionProps) {
  const [footerVisible, setFooterVisible] = useState(false);

  useEffect(() => {
    const footer = document.querySelector("footer");
    if (!footer) return;

    const observer = new IntersectionObserver(
      ([entry]) => setFooterVisible(entry.isIntersecting),
      { threshold: 0.05 },
    );
    observer.observe(footer);
    return () => observer.disconnect();
  }, []);

  return (
    <div
      className={cn(
        "fixed inset-x-0 bottom-0 z-40 border-t border-jp-border bg-jp-surface/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-jp-card backdrop-blur sm:hidden",
        footerVisible && "pointer-events-none opacity-0",
        className,
      )}
      data-testid="mobile-sticky-action"
    >
      {children}
    </div>
  );
}
