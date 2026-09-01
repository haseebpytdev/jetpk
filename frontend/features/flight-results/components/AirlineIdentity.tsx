import { cn } from "@/lib/cn";
import Image from "next/image";

type AirlineIdentityProps = {
  code?: string;
  name?: string;
  logoUrl?: string | null;
  size?: "sm" | "md" | "lg";
  className?: string;
};

const SIZE_MAP = {
  sm: { box: "h-8 w-8", text: "text-xs", img: 32 },
  md: { box: "h-10 w-10", text: "text-sm", img: 40 },
  lg: { box: "h-12 w-12", text: "text-base", img: 48 },
} as const;

/**
 * Airline row identity. Wrapper is sizing-only (no visible tile/border/card).
 */
export function AirlineIdentity({ code, name, logoUrl, size = "md", className }: AirlineIdentityProps) {
  const sizing = SIZE_MAP[size];
  const initials = (code ?? name ?? "?").slice(0, 2).toUpperCase();
  const alt = name ? `${name} logo` : code ? `${code} airline logo` : "Airline";

  return (
    <div className={cn("flex items-center gap-2.5", className)}>
      <div
        className={cn(
          "relative flex shrink-0 items-center justify-center overflow-hidden bg-transparent",
          sizing.box,
        )}
        aria-hidden={Boolean(logoUrl)}
        data-testid="airline-logo-container"
        data-logo-frame="none"
      >
        {logoUrl ? (
          <Image
            src={logoUrl}
            alt={alt}
            width={sizing.img}
            height={sizing.img}
            className="h-full w-full object-contain"
            unoptimized
          />
        ) : (
          <span
            className={cn(
              "inline-flex h-full w-full items-center justify-center rounded-jp-sm border border-jp-border bg-jp-surface font-semibold text-jp-text-muted",
              sizing.text,
            )}
          >
            {initials}
          </span>
        )}
      </div>
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
