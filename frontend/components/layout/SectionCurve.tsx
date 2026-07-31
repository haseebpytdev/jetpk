import { cn } from "@/lib/cn";

type SectionCurveProps = {
  className?: string;
  variant?: "wave" | "soft";
  flip?: boolean;
};

/** Shared section divider matching blueprint curve geometry. */
export function SectionCurve({ className, variant = "wave", flip = false }: SectionCurveProps) {
  const path =
    variant === "wave"
      ? "M0,32 C280,0 560,64 840,32 C980,16 1060,40 1122,24 L1122,0 L0,0 Z"
      : "M0,24 Q561,48 1122,24 L1122,0 L0,0 Z";

  return (
    <div
      className={cn("pointer-events-none w-full text-jp-page", flip && "rotate-180", className)}
      aria-hidden="true"
    >
      <svg viewBox="0 0 1122 32" preserveAspectRatio="none" className="block h-8 w-full">
        <path d={path} fill="currentColor" />
      </svg>
    </div>
  );
}
