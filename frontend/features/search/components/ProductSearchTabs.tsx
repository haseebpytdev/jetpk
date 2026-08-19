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
  group: "Group Ticketing",
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
              "relative shrink-0 border-b-2 pb-2 font-semibold transition-colors duration-ui",
              compact ? "text-jp-sm" : "text-jp-body",
              "focus-visible:outline-none focus-visible:shadow-jp-focus",
              selected
                ? "border-jp-primary text-jp-primary"
                : "border-transparent text-jp-muted hover:text-jp-text",
              compact && !selected && "text-white/80 hover:text-white",
            )}
          >
            {PRODUCT_LABELS[tab]}
          </button>
        );
      })}
    </div>
  );
}
