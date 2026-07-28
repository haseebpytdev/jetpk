"use client";

import { cn } from "@/lib/cn";
import { prefersReducedMotion } from "@/lib/motion";
import { useEffect, useRef, useState } from "react";

type AnimatedFlightPathProps = {
  className?: string;
  label?: string;
  variant?: "compact" | "hero";
};

/**
 * SVG dotted flight-path animation for homepage presentation.
 * Pauses when off-screen; static fallback for reduced motion.
 */
export function AnimatedFlightPath({
  className,
  label = "Decorative flight path",
  variant = "compact",
}: AnimatedFlightPathProps) {
  const rootRef = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(true);
  const [reducedMotion, setReducedMotion] = useState(false);

  useEffect(() => {
    setReducedMotion(prefersReducedMotion());
    const media = window.matchMedia("(prefers-reduced-motion: reduce)");
    const handler = () => setReducedMotion(media.matches);
    media.addEventListener("change", handler);
    return () => media.removeEventListener("change", handler);
  }, []);

  useEffect(() => {
    const node = rootRef.current;
    if (!node || reducedMotion) return;

    const observer = new IntersectionObserver(
      ([entry]) => setVisible(entry?.isIntersecting ?? true),
      { threshold: 0.1 },
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [reducedMotion]);

  const animate = visible && !reducedMotion;

  return (
    <div ref={rootRef} className={cn("relative w-full overflow-hidden", className)}>
      <svg
        viewBox={variant === "hero" ? "0 0 800 160" : "0 0 800 120"}
        className={cn("w-full text-jp-primary", variant === "hero" ? "h-28" : "h-24")}
        role="img"
        aria-label={label}
      >
        <defs>
          <pattern id="jp-flight-dots" width="8" height="8" patternUnits="userSpaceOnUse">
            <circle cx="2" cy="2" r="1.5" fill="currentColor" opacity="0.35" />
          </pattern>
        </defs>
        <path
          id="jp-flight-route"
          d="M20 80 C 180 20, 320 100, 480 50 S 700 30, 780 60"
          fill="none"
          stroke="url(#jp-flight-dots)"
          strokeWidth="4"
          strokeLinecap="round"
          className={cn(animate && "motion-safe:[stroke-dasharray:1200] motion-safe:[stroke-dashoffset:1200] motion-safe:animate-[flight-draw_8s_ease-in-out_infinite]")}
        />
        <g
          className={cn(animate && "motion-safe:animate-[flight-move_8s_ease-in-out_infinite]")}
          style={{ transformOrigin: "748px 48px" }}
        >
          <g transform="translate(748 48)">
            <path
              d="M0 8 L18 8 L14 4 M18 8 L14 12"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              fill="none"
            />
            <path d="M2 8 H10" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
          </g>
        </g>
        <circle cx="24" cy="78" r="5" fill="currentColor" opacity="0.5" />
        <circle cx="780" cy="60" r="5" fill="currentColor" opacity="0.35" />
      </svg>
    </div>
  );
}
