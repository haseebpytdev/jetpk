import { cn } from "@/lib/cn";
import Link from "next/link";

type PublicSectionHeaderProps = {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  ctaText?: string;
  ctaUrl?: string;
  className?: string;
};

export function PublicSectionHeader({
  eyebrow,
  title,
  subtitle,
  ctaText,
  ctaUrl,
  className,
}: PublicSectionHeaderProps) {
  return (
    <div className={cn("flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between", className)}>
      <div className="max-w-2xl">
        {eyebrow ? (
          <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-jp-primary">{eyebrow}</p>
        ) : null}
        <h2 className="font-display text-jp-h2 font-bold text-jp-text">{title}</h2>
        {subtitle ? <p className="mt-2 text-jp-body text-jp-muted">{subtitle}</p> : null}
      </div>
      {ctaText && ctaUrl ? (
        <Link href={ctaUrl} className="text-jp-sm font-semibold text-jp-primary hover:underline">
          {ctaText} →
        </Link>
      ) : null}
    </div>
  );
}
