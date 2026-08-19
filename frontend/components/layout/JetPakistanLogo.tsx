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
  logoHeight = 40,
}: JetPakistanLogoProps) {
  const isInverse = variant === "inverse";
  const imageSrc = resolveHeaderLogoUrl(logoUrl);

  return (
    <div className={cn("flex min-h-[var(--jp-header-logo-height,40px)] min-w-[8.5rem] items-center", className)}>
      <Image
        src={imageSrc}
        alt={brandName}
        width={Math.max(132, logoHeight * 4)}
        height={logoHeight}
        unoptimized={shouldUseUnoptimizedHeaderLogo(imageSrc)}
        className="object-contain object-left"
        style={{ height: `${logoHeight}px`, width: "auto", maxWidth: "min(200px, 44vw)" }}
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
