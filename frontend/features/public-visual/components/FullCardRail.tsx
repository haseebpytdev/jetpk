"use client";

import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { cn } from "@/lib/cn";
import { useCallback, useEffect, useRef, useState, type ReactNode } from "react";

function visibleCardCount(width: number): number {
  // Desktop PageContainer is ~1160–1240 minus horizontal padding, so the rail
  // itself is typically under 1180px. Use 1000 so desktop still shows 4 cards.
  if (width >= 1000) return 4;
  if (width >= 720) return 3;
  if (width >= 520) return 2;
  return 1;
}

type FullCardRailProps = {
  itemCount: number;
  ariaLabel: string;
  children: ReactNode;
  className?: string;
  prevLabel: string;
  nextLabel: string;
};

/**
 * Horizontal rail that always snaps to a whole number of full-width cards
 * (1–4 depending on viewport). Arrows appear only when content overflows.
 */
export function FullCardRail({
  itemCount,
  ariaLabel,
  children,
  className,
  prevLabel,
  nextLabel,
}: FullCardRailProps) {
  const scrollerRef = useRef<HTMLDivElement>(null);
  // Default to 1 so SSR/first paint never forces a 4-up basis on narrow viewports.
  const [count, setCount] = useState(1);
  const [canPrev, setCanPrev] = useState(false);
  const [canNext, setCanNext] = useState(false);

  const measure = useCallback(() => {
    const node = scrollerRef.current;
    if (!node) return;
    const nextCount = visibleCardCount(node.clientWidth);
    setCount(nextCount);
    const maxScroll = Math.max(0, node.scrollWidth - node.clientWidth - 2);
    setCanPrev(node.scrollLeft > 2);
    setCanNext(node.scrollLeft < maxScroll);
  }, []);

  useEffect(() => {
    const node = scrollerRef.current;
    if (!node) return;
    measure();
    const ro = new ResizeObserver(() => measure());
    ro.observe(node);
    node.addEventListener("scroll", measure, { passive: true });
    return () => {
      ro.disconnect();
      node.removeEventListener("scroll", measure);
    };
  }, [measure, itemCount]);

  const scrollByCards = (direction: -1 | 1) => {
    const node = scrollerRef.current;
    if (!node) return;
    const gap = 16;
    const cardWidth = (node.clientWidth - (count - 1) * gap) / count;
    node.scrollBy({ left: direction * (cardWidth + gap), behavior: "smooth" });
  };

  const needsNav = itemCount > count;
  const cardBasis = `calc((100% - ${(count - 1) * 1}rem) / ${count})`;

  return (
    <div className={cn("mt-jp-md flex w-full max-w-full items-center gap-2 overflow-hidden", className)} data-full-card-count={count}>
      {needsNav ? (
        <SecondaryButton
          type="button"
          aria-label={prevLabel}
          className="!min-h-9 !min-w-9 shrink-0 !rounded-full !px-0"
          onClick={() => scrollByCards(-1)}
          disabled={!canPrev}
        >
          ←
        </SecondaryButton>
      ) : null}
      <div
        ref={scrollerRef}
        className="flex min-w-0 w-full max-w-full flex-1 snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain scroll-smooth pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        role="region"
        aria-label={ariaLabel}
        style={{ ["--jp-card-basis" as string]: cardBasis }}
      >
        {children}
      </div>
      {needsNav ? (
        <SecondaryButton
          type="button"
          aria-label={nextLabel}
          className="!min-h-9 !min-w-9 shrink-0 !rounded-full !px-0"
          onClick={() => scrollByCards(1)}
          disabled={!canNext}
        >
          →
        </SecondaryButton>
      ) : null}
    </div>
  );
}

export const fullCardArticleClass =
  "shrink-0 snap-start overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card [flex-basis:var(--jp-card-basis)] [width:var(--jp-card-basis)] [max-width:var(--jp-card-basis)]";
