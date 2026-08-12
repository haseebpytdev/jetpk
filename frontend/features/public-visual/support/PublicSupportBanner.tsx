import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { ScrollReveal } from "@/features/motion";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import type { HomepageSupportCta } from "../types/homepage";

type PublicSupportBannerProps = {
  support: HomepageSupportCta;
};

export function PublicSupportBanner({ support }: PublicSupportBannerProps) {
  if (!support.enabled) return null;

  return (
    <ScrollReveal as="section">
      <section className="border-y border-jp-border bg-gradient-to-r from-jp-brand-soft via-jp-surface to-jp-page">
        <PageContainer className="grid items-center gap-jp-lg py-8 sm:py-10 lg:grid-cols-[1.2fr_minmax(0,16rem)]">
          <div className="max-w-2xl">
            {support.eyebrow ? (
              <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-jp-primary">{support.eyebrow}</p>
            ) : null}
            <h2 className="mt-2 font-display text-jp-h2 font-bold text-jp-text">{support.title}</h2>
            {support.subtitle ? <p className="mt-2 text-jp-body text-jp-muted">{support.subtitle}</p> : null}
            <div className="mt-5 flex flex-col gap-3 sm:flex-row">
              {support.chatEnabled && support.chatHref ? (
                support.chatHref.startsWith("http") || support.chatHref.startsWith("tel:") || support.chatHref.startsWith("mailto:") ? (
                  <a href={support.chatHref}>
                    <PrimaryButton>{support.chatLabel}</PrimaryButton>
                  </a>
                ) : (
                  <Link href={support.chatHref}>
                    <PrimaryButton>{support.chatLabel}</PrimaryButton>
                  </Link>
                )
              ) : null}
              {support.callEnabled && support.callHref ? (
                support.callHref.startsWith("http") || support.callHref.startsWith("tel:") || support.callHref.startsWith("mailto:") ? (
                  <a href={support.callHref}>
                    <SecondaryButton>{support.callLabel}</SecondaryButton>
                  </a>
                ) : (
                  <Link href={support.callHref}>
                    <SecondaryButton>{support.callLabel}</SecondaryButton>
                  </Link>
                )
              ) : null}
            </div>
          </div>

          <div
            className="relative hidden min-h-[9.5rem] overflow-hidden rounded-jp-lg border border-jp-brand-border/40 bg-gradient-to-br from-jp-brand-soft via-jp-surface to-jp-surface-muted lg:block"
            aria-hidden="true"
            data-media-slot="support-callout-illustration"
          >
            <svg viewBox="0 0 280 180" className="h-full w-full text-jp-brand" fill="none">
              <circle cx="210" cy="70" r="42" stroke="currentColor" strokeWidth="1.5" className="opacity-30" />
              <path
                d="M36 120 C90 50, 140 140, 210 70"
                stroke="currentColor"
                strokeWidth="2"
                strokeDasharray="5 7"
                strokeLinecap="round"
                className="opacity-45"
              />
              <rect x="48" y="48" width="88" height="64" rx="14" stroke="currentColor" strokeWidth="1.8" className="opacity-55" />
              <path d="M66 72h52M66 88h36" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" className="opacity-70" />
              <circle cx="210" cy="70" r="4" fill="currentColor" />
            </svg>
          </div>
        </PageContainer>
      </section>
    </ScrollReveal>
  );
}
