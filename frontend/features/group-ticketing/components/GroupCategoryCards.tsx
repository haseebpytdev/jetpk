"use client";

import Link from "next/link";
import { cn } from "@/lib/cn";
import type { GroupSearchFacetOption } from "../types";

type GroupCategoryCardsProps = {
  categories: GroupSearchFacetOption[];
  /** Selection mode (results refinement) vs link mode (landing deep links). */
  mode?: "select" | "link";
  selected?: string;
  onSelect?: (value: string) => void;
  disabled?: boolean;
  className?: string;
};

/**
 * Dynamic category tiles from inventory facets — never hardcoded category lists.
 */
export function GroupCategoryCards({
  categories,
  mode = "select",
  selected = "",
  onSelect,
  disabled = false,
  className,
}: GroupCategoryCardsProps) {
  if (categories.length === 0) {
    return null;
  }

  return (
    <div
      className={cn(className)}
      data-testid="group-category-cards"
      role="list"
      aria-label="Group destinations"
    >
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {categories.map((category) => {
          const active = selected === category.value;
          const count = category.inventory_count;
          const href = `/groups/search?category=${encodeURIComponent(category.value)}`;
          const body = (
            <>
              <div className="flex items-start justify-between gap-2">
                <span className="text-jp-base font-semibold text-jp-text">{category.label}</span>
                <span className="mt-0.5 text-jp-muted" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
                    <path d="M4 10h16M7 10V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v3M6 10v8h12v-8" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                </span>
              </div>
              {typeof count === "number" ? (
                <p className="mt-2 text-jp-sm text-jp-muted">
                  {count} departure{count === 1 ? "" : "s"} available
                </p>
              ) : (
                <p className="mt-2 text-jp-sm text-jp-muted">Group inventory</p>
              )}
            </>
          );

          const tileClass = cn(
            "min-h-[7.5rem] rounded-jp-lg border px-5 py-4 text-left transition-colors focus-visible:outline-none focus-visible:shadow-jp-focus",
            active
              ? "border-jp-primary bg-jp-primary-soft"
              : "border-jp-border bg-jp-surface hover:border-jp-primary/40",
            disabled && "cursor-not-allowed opacity-60",
          );

          if (mode === "link") {
            return (
              <Link
                key={category.value}
                href={href}
                role="listitem"
                data-testid={`group-category-card-${category.value}`}
                className={tileClass}
                aria-disabled={disabled || undefined}
                tabIndex={disabled ? -1 : undefined}
                onClick={(event) => {
                  if (disabled) event.preventDefault();
                }}
              >
                {body}
              </Link>
            );
          }

          return (
            <button
              key={category.value}
              type="button"
              role="listitem"
              disabled={disabled}
              aria-pressed={active}
              data-testid={`group-category-card-${category.value}`}
              onClick={() => onSelect?.(active ? "all" : category.value)}
              className={tileClass}
            >
              {body}
            </button>
          );
        })}
      </div>
    </div>
  );
}
