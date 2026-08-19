"use client";

import { cn } from "@/lib/cn";
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
            className={cn(
              "relative shrink-0 border-b-2 pb-2 font-semibold transition-colors duration-ui",
              compact ? "text-jp-sm" : "text-jp-body",
              "focus-visible:outline-none focus-visible:shadow-jp-focus",
              selected
                ? "border-jp-primary text-jp-primary"
                : "border-transparent text-jp-muted hover:text-jp-text",
            )}
          >
            {PRODUCT_LABELS[tab]}
          </button>
        );
      })}
    </div>
  );
}
