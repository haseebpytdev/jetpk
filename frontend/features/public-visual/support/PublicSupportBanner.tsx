import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { cn } from "@/lib/cn";
import type { HomepageSupportCta } from "../types/homepage";

type PublicSupportBannerProps = {
  support: HomepageSupportCta;
  compact?: boolean;
};

export function PublicSupportBanner({ support, compact = false }: PublicSupportBannerProps) {
  if (!support.enabled) return null;

  return (
    <section className="border-y border-jp-border bg-jp-primary-soft/50">
      <PageContainer className={cn("flex items-center justify-between gap-4", compact ? "py-2" : "py-4 lg:py-5")}>
        <div className={cn("flex items-center", compact ? "gap-3" : "gap-4")}>
          <div
            className={cn(
              "flex shrink-0 items-center justify-center rounded-full bg-jp-primary text-white",
              compact ? "h-10 w-10" : "h-14 w-14",
            )}
          >
            <svg viewBox="0 0 24 24" className={compact ? "h-5 w-5" : "h-7 w-7"} aria-hidden="true">
              <path d="M4 14v3a2 2 0 0 0 2 2h1v-7H5a1 1 0 0 0-1 1Zm15-5a7 7 0 0 0-14 0v5h14V9Zm3 5h-1v7h1a2 2 0 0 0 2-2v-3a1 1 0 0 0-1-1Z" fill="currentColor" />
            </svg>
          </div>
          <div className="max-w-xl">
            {support.eyebrow && !compact ? (
              <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-jp-primary">{support.eyebrow}</p>
            ) : null}
            <h2 className={cn("font-display font-bold text-jp-text", compact ? "text-jp-sm" : "text-jp-md lg:text-jp-lg")}>
              {support.title}
            </h2>
            {support.subtitle && !compact ? <p className="mt-1 text-jp-sm text-jp-muted">{support.subtitle}</p> : null}
          </div>
        </div>

        {(support.chatEnabled && support.chatHref) || (support.callEnabled && support.callHref) ? (
          <div className="shrink-0">
            {support.chatEnabled && support.chatHref ? (
              <Link href={support.chatHref}>
                <PrimaryButton className="whitespace-nowrap">
                  {support.chatLabel} →
                </PrimaryButton>
              </Link>
            ) : support.callEnabled && support.callHref ? (
              <Link href={support.callHref}>
                <PrimaryButton className="whitespace-nowrap">
                  {support.callLabel} →
                </PrimaryButton>
              </Link>
            ) : null}
          </div>
        ) : null}
      </PageContainer>
    </section>
  );
}
