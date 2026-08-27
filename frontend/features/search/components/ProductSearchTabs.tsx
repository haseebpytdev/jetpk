"use client";

import { cn } from "@/lib/cn";
import { useCallback } from "react";
import type { ProductTab } from "../types";

type ProductSearchTabsProps = {
  productTab: ProductTab;
  onProductTabChange: (tab: ProductTab) => void;
  compact?: boolean;
};

const PRODUCT_TABS: ProductTab[] = ["flights", "group"];

const PRODUCT_LABELS: Record<ProductTab, string> = {
  flights: "Flights",
  group: "Groups",
};

/** Recognizable forward-flying airplane (not an abstract arrow). */
function FlightsIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M2.5 12.5 21 4l-3.5 16-4.5-4.5L8.5 20l-1-4.5L2.5 12.5Z" />
      <path d="M21 4 10.5 14.5" />
    </svg>
  );
}

/** Multiple people — unmistakable group/users mark. */
function GroupsIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <circle cx="9" cy="7" r="3" />
      <circle cx="17" cy="8" r="2.5" />
      <path d="M3.5 19c.5-3 2.8-5 5.5-5s5 2 5.5 5" />
      <path d="M14 14.2c1.7-.4 3.5.3 4.5 2.3" />
    </svg>
  );
}

const PRODUCT_ICONS: Record<ProductTab, (props: { className?: string }) => React.ReactNode> = {
  flights: FlightsIcon,
  group: GroupsIcon,
};

export function ProductSearchTabs({
  productTab,
  onProductTabChange,
  compact = false,
}: ProductSearchTabsProps) {
  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLButtonElement>, current: ProductTab) => {
      const index = PRODUCT_TABS.indexOf(current);
      if (index === -1) return;

      let nextIndex = index;
      if (event.key === "ArrowRight") nextIndex = (index + 1) % PRODUCT_TABS.length;
      if (event.key === "ArrowLeft") nextIndex = (index - 1 + PRODUCT_TABS.length) % PRODUCT_TABS.length;
      if (event.key === "Home") nextIndex = 0;
      if (event.key === "End") nextIndex = PRODUCT_TABS.length - 1;

      if (nextIndex !== index) {
        event.preventDefault();
        const nextTab = PRODUCT_TABS[nextIndex]!;
        onProductTabChange(nextTab);
        document.getElementById(`product-tab-${nextTab}`)?.focus();
      }
    },
    [onProductTabChange],
  );

  return (
    <div
      role="tablist"
      aria-label="Search product"
      className="flex items-center gap-4"
      data-testid="product-search-tabs"
    >
      {PRODUCT_TABS.map((tab) => {
        const selected = productTab === tab;
        const Icon = PRODUCT_ICONS[tab];
        return (
          <button
            key={tab}
            id={`product-tab-${tab}`}
            type="button"
            role="tab"
            aria-selected={selected}
            tabIndex={selected ? 0 : -1}
            data-testid={`product-tab-${tab}`}
            onClick={() => onProductTabChange(tab)}
            onKeyDown={(event) => handleKeyDown(event, tab)}
            className={cn(
              "relative inline-flex shrink-0 items-center gap-2 border-b-2 pb-2 font-semibold transition-colors duration-ui",
              compact ? "text-jp-sm" : "text-jp-body",
              "focus-visible:outline-none focus-visible:shadow-jp-focus",
              selected
                ? "border-jp-primary text-jp-primary"
                : "border-transparent text-jp-text/70 hover:text-jp-text",
            )}
          >
            <Icon className="opacity-90" />
            <span>{PRODUCT_LABELS[tab]}</span>
          </button>
        );
      })}
    </div>
  );
}
