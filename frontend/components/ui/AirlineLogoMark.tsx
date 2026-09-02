import { cn } from "@/lib/cn";
import Image from "next/image";

type AirlineLogoMarkProps = {
  code?: string | null;
  name?: string | null;
  logoUrl?: string | null;
  size?: number;
  className?: string;
  /** When true, hide decorative mark from AT tree (parent supplies name). */
  decorative?: boolean;
};

/**
 * Square transparent airline logo stage — no radius, border, or app tile background.
 * Intrinsic baked frames in PNG assets cannot be removed via CSS; prefer transparent assets.
 */
export function AirlineLogoMark({
  code,
  name,
  logoUrl,
  size = 40,
  className,
  decorative = false,
}: AirlineLogoMarkProps) {
  const initials = (code ?? name ?? "?").slice(0, 2).toUpperCase();
  const alt = name ? `${name} logo` : code ? `${code} airline logo` : "Airline logo";

  return (
    <span
      className={cn(
        "relative inline-flex shrink-0 items-center justify-center overflow-hidden bg-transparent",
        className,
      )}
      style={{
        width: size,
        height: size,
        borderRadius: 0,
        border: "none",
        background: "transparent",
      }}
      data-testid="airline-logo-mark"
      data-logo-frame="square-none"
      data-logo-radius="0"
      aria-hidden={decorative || Boolean(logoUrl) || undefined}
    >
      {logoUrl ? (
        <Image
          src={logoUrl}
          alt={decorative ? "" : alt}
          width={size}
          height={size}
          className="h-full w-full object-contain"
          unoptimized
          loading="lazy"
          decoding="async"
        />
      ) : (
        <span
          className="inline-flex h-full w-full items-center justify-center bg-jp-surface-muted text-[11px] font-semibold tracking-wide text-jp-text-muted"
          style={{ borderRadius: 0 }}
          data-testid="airline-logo-fallback"
        >
          {initials}
        </span>
      )}
    </span>
  );
}
