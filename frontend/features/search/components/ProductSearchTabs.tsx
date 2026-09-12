"use client";

import { cn } from "@/lib/cn";
import { UI_ICON_COMPACT_CLASS, UI_ICON_STROKE } from "@/lib/ui-icon";
import { Plane, Users } from "lucide-react";
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

const PRODUCT_ICONS: Record<ProductTab, React.ReactNode> = {
  flights: <Plane className={cn(UI_ICON_COMPACT_CLASS, "opacity-90")} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />,
  group: <Users className={cn(UI_ICON_COMPACT_CLASS, "opacity-90")} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />,
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
              "relative inline-flex shrink-0 items-center gap-2 border-b-2 pb-2 font-semibold transition-colors duration-ui",
              compact ? "text-jp-sm" : "text-jp-body",
              "focus-visible:outline-none focus-visible:shadow-jp-focus",
              selected
                ? "border-jp-primary text-jp-primary"
                : "border-transparent text-jp-text/70 hover:text-jp-text",
            )}
          >
            {PRODUCT_ICONS[tab]}
            <span>{PRODUCT_LABELS[tab]}</span>
          </button>
        );
      })}
    </div>
  );
}
