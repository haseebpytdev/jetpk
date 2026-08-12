import { cn } from "@/lib/cn";
import Image from "next/image";

type JetPakistanLogoProps = {
  className?: string;
  variant?: "default" | "inverse";
  showTagline?: boolean;
  logoUrl?: string | null;
  brandName?: string;
  logoHeight?: number;
};

function shouldUseUnoptimizedLogo(src: string): boolean {
  return (
    src.startsWith("http://") ||
    src.startsWith("https://") ||
    src.startsWith("/storage/") ||
    src.startsWith("/client-assets/")
  );
}

export function JetPakistanLogo({
  className,
  variant = "default",
  showTagline = true,
  logoUrl,
  brandName = "JetPakistan",
  logoHeight = 36,
}: JetPakistanLogoProps) {
  const isInverse = variant === "inverse";
  const resolvedLogo = logoUrl?.trim() ?? "";

  if (resolvedLogo !== "") {
    return (
      <div className={cn("flex min-w-0 items-center", className)}>
        <Image
          src={resolvedLogo}
          alt={brandName}
          width={Math.max(120, logoHeight * 4)}
          height={logoHeight}
          unoptimized={shouldUseUnoptimizedLogo(resolvedLogo)}
          className="h-auto w-auto max-h-[var(--jp-header-logo-height,36px)] max-w-[min(180px,42vw)] object-contain object-left"
          style={{ maxHeight: `${logoHeight}px` }}
        />
      </div>
    );
  }

  return (
    <div className={cn("flex items-center gap-3", className)}>
      <span
        aria-hidden="true"
        className={cn(
          "flex h-10 w-10 shrink-0 items-center justify-center rounded-jp-md",
          isInverse ? "bg-white/10" : "bg-jp-primary-soft",
        )}
      >
        <svg viewBox="0 0 32 32" className="h-6 w-6" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M6 18.5L14 10.5L18 14.5L26 8"
            stroke={isInverse ? "#FFFFFF" : "var(--jp-primary)"}
            strokeWidth="2.2"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
          <path
            d="M8 22H24"
            stroke={isInverse ? "#FFFFFF" : "var(--jp-primary)"}
            strokeWidth="2.2"
            strokeLinecap="round"
          />
          <path
            d="M20 8L26 8L24 12"
            fill={isInverse ? "#FFFFFF" : "var(--jp-primary)"}
          />
        </svg>
      </span>
      <div className="min-w-0">
        <span
          className={cn(
            "block font-sans text-jp-lg font-bold leading-tight",
            isInverse ? "text-white" : "text-jp-text",
          )}
        >
          {brandName}
        </span>
        {showTagline ? (
          <span
            className={cn(
              "block text-[10px] font-semibold uppercase tracking-[0.18em]",
              isInverse ? "text-white/80" : "text-jp-muted",
            )}
          >
            Fly Smart, Fly Easy
          </span>
        ) : null}
      </div>
    </div>
  );
}
