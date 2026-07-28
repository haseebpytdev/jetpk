import { cn } from "@/lib/cn";

type JetPakistanLogoProps = {
  className?: string;
  variant?: "default" | "inverse";
  showTagline?: boolean;
};

export function JetPakistanLogo({
  className,
  variant = "default",
  showTagline = true,
}: JetPakistanLogoProps) {
  const isInverse = variant === "inverse";

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
            "block font-display text-jp-lg font-bold leading-tight",
            isInverse ? "text-white" : "text-jp-text",
          )}
        >
          JetPakistan
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
