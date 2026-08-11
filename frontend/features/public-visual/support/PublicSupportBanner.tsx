import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { ScrollReveal } from "@/features/motion";
import { ImageSlot } from "@/components/ui/ImageSlot";
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
      <section className="border-y border-jp-border bg-gradient-to-r from-jp-primary-soft via-jp-surface to-jp-page">
      <PageContainer className="grid items-center gap-jp-lg py-jp-3xl lg:grid-cols-[1fr_auto]">
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
        {support.image ? (
          <ImageSlot
            src={support.image}
            alt=""
            decorative
            width={280}
            height={200}
            className="hidden lg:block"
          />
        ) : null}
      </PageContainer>
      </section>
    </ScrollReveal>
  );
}
