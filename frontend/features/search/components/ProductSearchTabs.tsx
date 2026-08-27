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

function FlightsIcon({ className }: { className?: string }) {
  return (
    <svg className={className} width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true">
      <path d="M10.5 3.5 21 12l-10.5 8.5V14H3v-4h7.5V3.5Z" strokeLinejoin="round" />
    </svg>
  );
}

function GroupsIcon({ className }: { className?: string }) {
  return (
    <svg className={className} width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true">
      <path
        d="M16 11a3 3 0 1 0-2.83-4M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM4.5 19a4.5 4.5 0 0 1 7 0M12.5 19a4.5 4.5 0 0 1 7 0"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
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
