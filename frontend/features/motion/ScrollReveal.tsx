"use client";

import { cn } from "@/lib/cn";
import { type ReactNode, useEffect, useRef } from "react";
import { observeRevealElement } from "./reveal-element";
import { usePrefersReducedMotion } from "./prefers-reduced-motion";

type ScrollRevealProps = {
  children: ReactNode;
  className?: string;
  /** Stagger delay in ms for card groups (0–4). */
  staggerIndex?: number;
  as?: "div" | "section" | "article" | "li";
};

export function ScrollReveal({
  children,
  className,
  staggerIndex = 0,
  as: Tag = "div",
}: ScrollRevealProps) {
  const reduced = usePrefersReducedMotion();
  const ref = useRef<HTMLElement | null>(null);

  useEffect(() => {
    const element = ref.current;
    if (!element) return undefined;
    return observeRevealElement(element, { reduced });
  }, [reduced]);

  const staggerStyle =
    staggerIndex > 0 && !reduced
      ? ({ transitionDelay: `${Math.min(staggerIndex, 4) * 60}ms` } as const)
      : undefined;

  return (
    <Tag
      ref={ref as never}
      className={cn("jp-scroll-reveal", reduced && "jp-scroll-reveal--reduced", className)}
      style={staggerStyle}
      data-revealed="false"
    >
      {children}
    </Tag>
  );
}
