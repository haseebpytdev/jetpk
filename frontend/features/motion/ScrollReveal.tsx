"use client";

import { cn } from "@/lib/cn";
import { type ReactNode } from "react";
import { usePrefersReducedMotion } from "./prefers-reduced-motion";
import { useSharedIntersectionObserver } from "./use-shared-intersection-observer";

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
  const ref = useSharedIntersectionObserver((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add("jp-reveal-visible");
      entry.target.setAttribute("data-revealed", "true");
    }
  });

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
