import { cn } from "@/lib/cn";
import {
  resolveHeaderLogoUrl,
  shouldUseUnoptimizedHeaderLogo,
} from "@/lib/branding/resolve-header-logo";
import Image from "next/image";

type JetPakistanLogoProps = {
  className?: string;
  variant?: "default" | "inverse";
  showTagline?: boolean;
  logoUrl?: string | null;
  brandName?: string;
  logoHeight?: number;
};

export function JetPakistanLogo({
  className,
  variant = "default",
  showTagline = true,
  logoUrl,
  brandName = "JetPakistan",
  logoHeight = 36,
}: JetPakistanLogoProps) {
  const isInverse = variant === "inverse";
  const imageSrc = resolveHeaderLogoUrl(logoUrl);

  return (
    <div className={cn("flex min-h-[var(--jp-header-logo-height,36px)] min-w-[7.5rem] items-center", className)}>
      <Image
        src={imageSrc}
        alt={brandName}
        width={Math.max(120, logoHeight * 4)}
        height={logoHeight}
        unoptimized={shouldUseUnoptimizedHeaderLogo(imageSrc)}
        className="h-auto w-auto max-h-[var(--jp-header-logo-height,36px)] max-w-[min(180px,42vw)] object-contain object-left"
        style={{ maxHeight: `${logoHeight}px` }}
        data-testid="jetpakistan-header-logo"
      />
      {showTagline ? (
        <span
          className={cn(
            "ml-3 hidden min-w-0 text-[10px] font-semibold uppercase tracking-[0.18em] lg:block",
            isInverse ? "text-white/80" : "text-jp-muted",
          )}
        >
          Fly Smart, Fly Easy
        </span>
      ) : null}
    </div>
  );
}
