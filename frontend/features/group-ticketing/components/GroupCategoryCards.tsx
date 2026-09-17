"use client";

import Image from "next/image";
import Link from "next/link";
import { cn } from "@/lib/cn";
import type { GroupSearchFacetOption } from "../types";

type GroupCategoryCardsProps = {
  categories: GroupSearchFacetOption[];
  mode?: "select" | "link";
  selected?: string;
  onSelect?: (value: string) => void;
  disabled?: boolean;
  className?: string;
  /** Media cards (landing) vs compact tiles (search refinement). */
  variant?: "media" | "compact";
};

const FALLBACK_GRADIENT =
  "linear-gradient(135deg, rgba(15, 61, 46, 0.92) 0%, rgba(28, 92, 70, 0.88) 55%, rgba(12, 40, 32, 0.94) 100%)";

/**
 * Dynamic category tiles from inventory facets.
 * Media variant uses CMS/admin homepage-tile images when present.
 */
export function GroupCategoryCards({
  categories,
  mode = "select",
  selected = "",
  onSelect,
  disabled = false,
  className,
  variant = "compact",
}: GroupCategoryCardsProps) {
  if (categories.length === 0) {
    return null;
  }

  const media = variant === "media";

  return (
    <div
      className={cn(className)}
      data-testid="group-category-cards"
      data-variant={variant}
      role="list"
      aria-label="Group destinations"
    >
      <div className={cn("grid gap-4", media ? "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" : "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3")}>
        {categories.map((category) => {
          const active = selected === category.value;
          const count = category.inventory_count;
          const href = `/groups/search?category=${encodeURIComponent(category.value)}`;
          const imageUrl = category.image_url?.trim() || null;

          const mediaBody = (
            <>
              <div className="absolute inset-0">
                {imageUrl ? (
                  <Image
                    src={imageUrl}
                    alt=""
                    fill
                    className="object-cover"
                    sizes="(max-width: 640px) 100vw, 25vw"
                    unoptimized
                  />
                ) : (
                  <div className="h-full w-full" style={{ background: FALLBACK_GRADIENT }} data-testid="group-category-fallback-media" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/35 to-black/10" />
              </div>
              <div className="relative z-[1] mt-auto space-y-1 p-4 text-white">
                <p className="text-lg font-semibold tracking-[-0.02em]">{category.label}</p>
                {category.subtitle ? <p className="text-jp-sm text-white/85">{category.subtitle}</p> : null}
                <p className="text-jp-xs font-medium text-white/80">
                  {typeof count === "number"
                    ? `${count} departure${count === 1 ? "" : "s"} available`
                    : "Group inventory"}
                </p>
              </div>
            </>
          );

          const compactBody = (
            <>
              <div className="flex items-start justify-between gap-2">
                <span className="text-jp-base font-semibold text-jp-text">{category.label}</span>
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

          const tileClass = media
            ? cn(
                "relative flex min-h-[11.5rem] overflow-hidden rounded-jp-xl border text-left transition focus-visible:outline-none focus-visible:shadow-jp-focus",
                active ? "border-jp-primary ring-2 ring-jp-primary/40" : "border-transparent hover:brightness-105",
                disabled && "cursor-not-allowed opacity-60",
              )
            : cn(
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
                {media ? mediaBody : compactBody}
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
              {media ? mediaBody : compactBody}
            </button>
          );
        })}
      </div>
    </div>
  );
}
