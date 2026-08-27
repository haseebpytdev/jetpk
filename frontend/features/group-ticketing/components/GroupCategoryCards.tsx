"use client";

import { cn } from "@/lib/cn";
import type { GroupSearchFacetOption } from "../types";

type GroupCategoryCardsProps = {
  categories: GroupSearchFacetOption[];
  selected: string;
  onSelect: (value: string) => void;
  disabled?: boolean;
  className?: string;
};

export function GroupCategoryCards({
  categories,
  selected,
  onSelect,
  disabled = false,
  className,
}: GroupCategoryCardsProps) {
  if (categories.length === 0) {
    return null;
  }

  return (
    <div
      className={cn("mt-4", className)}
      data-testid="group-category-cards"
      role="list"
      aria-label="Group destinations"
    >
      <div className="flex gap-3 overflow-x-auto pb-1 sm:grid sm:grid-cols-2 sm:overflow-visible lg:grid-cols-3 xl:grid-cols-4">
        {categories.map((category) => {
          const active = selected === category.value;
          const count = category.inventory_count;
          return (
            <button
              key={category.value}
              type="button"
              role="listitem"
              disabled={disabled}
              aria-pressed={active}
              data-testid={`group-category-card-${category.value}`}
              onClick={() => onSelect(active ? "all" : category.value)}
              className={cn(
                "min-w-[10.5rem] shrink-0 rounded-jp-lg border px-4 py-3 text-left transition-colors focus-visible:outline-none focus-visible:shadow-jp-focus sm:min-w-0",
                active
                  ? "border-jp-primary bg-jp-primary-soft text-jp-primary"
                  : "border-jp-border bg-jp-surface text-jp-text hover:border-jp-primary/40",
                disabled && "cursor-not-allowed opacity-60",
              )}
            >
              <div className="flex items-start justify-between gap-2">
                <span className="text-jp-sm font-semibold">{category.label}</span>
                <span className="mt-0.5 text-jp-muted" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
                    <path d="M4 10h16M7 10V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v3M6 10v8h12v-8" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                </span>
              </div>
              {typeof count === "number" ? (
                <p className="mt-1 text-jp-xs text-jp-muted">
                  {count} departure{count === 1 ? "" : "s"}
                </p>
              ) : (
                <p className="mt-1 text-jp-xs text-jp-muted">Group inventory</p>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
