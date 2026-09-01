import { AirlineLogoMark } from "@/components/ui/AirlineLogoMark";
import { cn } from "@/lib/cn";

type AirlineIdentityProps = {
  code?: string;
  name?: string;
  logoUrl?: string | null;
  size?: "sm" | "md" | "lg";
  className?: string;
};

const SIZE_MAP = {
  sm: { px: 32, text: "text-xs" },
  md: { px: 40, text: "text-sm" },
  lg: { px: 48, text: "text-base" },
} as const;

/**
 * Airline row identity. Logo stage is square, transparent, radius 0 (no tile/border).
 */
export function AirlineIdentity({ code, name, logoUrl, size = "md", className }: AirlineIdentityProps) {
  const sizing = SIZE_MAP[size];

  return (
    <div className={cn("flex items-center gap-2.5", className)}>
      <AirlineLogoMark
        code={code}
        name={name}
        logoUrl={logoUrl}
        size={sizing.px}
        decorative
      />
      <div className="min-w-0">
        {name ? (
          <p className="truncate text-sm font-semibold leading-tight text-jp-text" title={name}>
            {name}
          </p>
        ) : null}
        {code ? (
          <p className="mt-0.5 text-[11px] font-medium tracking-wide text-jp-text-muted">{code}</p>
        ) : null}
      </div>
    </div>
  );
}
