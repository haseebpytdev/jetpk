import { cn } from "@/lib/cn";
import Link from "next/link";

type PublicSectionHeaderProps = {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  ctaText?: string;
  ctaUrl?: string;
  className?: string;
  compact?: boolean;
};

export function PublicSectionHeader({
  eyebrow,
  title,
  subtitle,
  ctaText,
  ctaUrl,
  className,
  compact = false,
}: PublicSectionHeaderProps) {
  return (
    <div
      className={cn(
        "flex flex-col sm:flex-row sm:items-end sm:justify-between",
        compact ? "gap-1" : "gap-4",
        className,
      )}
    >
      <div className={cn(compact ? "max-w-xl" : "max-w-2xl")}>
        {eyebrow && !compact ? (
          <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-jp-primary">{eyebrow}</p>
        ) : null}
        <h2
          className={cn(
            "font-display font-bold text-jp-text",
            compact ? "text-base leading-tight" : "text-jp-h2",
          )}
        >
          {title}
        </h2>
        {subtitle && !compact ? <p className="mt-2 text-jp-body text-jp-muted">{subtitle}</p> : null}
      </div>
      {ctaText && ctaUrl ? (
        <Link
          href={ctaUrl}
          className={cn(
            "shrink-0 font-semibold text-jp-primary hover:underline",
            compact ? "text-jp-xs" : "text-jp-sm",
          )}
        >
          {ctaText} →
        </Link>
      ) : null}
    </div>
  );
}
