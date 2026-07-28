import { cn } from "@/lib/cn";

type AnimatedFlightPathProps = {
  className?: string;
  label?: string;
};

/**
 * Lightweight SVG flight-path foundation for JP-FE-01.
 * Full homepage choreography belongs to JP-FE-02+.
 */
export function AnimatedFlightPath({
  className,
  label = "Decorative flight path",
}: AnimatedFlightPathProps) {
  return (
    <div className={cn("relative w-full overflow-hidden", className)}>
      <svg
        viewBox="0 0 800 120"
        className="h-24 w-full text-jp-primary motion-safe:animate-[flight-drift_12s_ease-in-out_infinite] motion-reduce:animate-none"
        role="img"
        aria-label={label}
      >
        <defs>
          <pattern id="jp-flight-dots" width="8" height="8" patternUnits="userSpaceOnUse">
            <circle cx="2" cy="2" r="1.5" fill="currentColor" opacity="0.35" />
          </pattern>
        </defs>
        <path
          d="M20 80 C 180 20, 320 100, 480 50 S 700 30, 780 60"
          fill="none"
          stroke="url(#jp-flight-dots)"
          strokeWidth="4"
          strokeLinecap="round"
        />
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
        <circle cx="24" cy="78" r="5" fill="currentColor" opacity="0.5" />
      </svg>
    </div>
  );
}
