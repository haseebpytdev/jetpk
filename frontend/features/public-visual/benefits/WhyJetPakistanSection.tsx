"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { ScrollReveal } from "@/features/motion";
import type { HomepageSectionHeader, HomepageWhyCard } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";

type WhyJetPakistanSectionProps = HomepageSectionHeader & {
  cards: HomepageWhyCard[];
};

export function WhyJetPakistanSection({
  enabled,
  eyebrow,
  title,
  subtitle,
  cards,
}: WhyJetPakistanSectionProps) {
  if (!enabled || cards.length === 0) return null;

  return (
    <ScrollReveal as="section">
      <SectionContainer>
        <PageContainer>
          <PublicSectionHeader eyebrow={eyebrow} title={title || "Why JetPakistan"} subtitle={subtitle} />

          <div className="mt-jp-lg grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((card, index) => (
              <ScrollReveal key={card.id} as="article" staggerIndex={index + 1}>
                <article className="rounded-jp-card border border-jp-border bg-jp-surface p-jp-lg shadow-jp-card">
                  {card.num ? <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-primary">{card.num}</p> : null}
                  <h3 className="mt-2 font-display text-jp-md font-semibold text-jp-text">{card.title}</h3>
                  {card.text ? <p className="mt-2 text-jp-sm leading-relaxed text-jp-muted">{card.text}</p> : null}
                </article>
              </ScrollReveal>
            ))}
          </div>
        </PageContainer>
      </SectionContainer>
    </ScrollReveal>
  );
}
