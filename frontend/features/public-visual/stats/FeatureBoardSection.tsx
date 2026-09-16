"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { ScrollReveal } from "@/features/motion";
import type { HomepageFeatureStat } from "../types/homepage";

type FeatureBoardSectionProps = {
  enabled: boolean;
  items: HomepageFeatureStat[];
};

export function FeatureBoardSection({ enabled, items }: FeatureBoardSectionProps) {
  if (!enabled || items.length === 0) return null;

  return (
    <ScrollReveal as="section" data-testid="homepage-feature-board-section">
      <SectionContainer className="border-y border-jp-border/70 bg-jp-surface-muted/40">
        <PageContainer>
          <h2 className="sr-only">JetPakistan highlights</h2>
          <ul className="grid gap-4 py-jp-md sm:grid-cols-2 lg:grid-cols-5">
            {items.map((item, index) => (
              <ScrollReveal
                key={item.id}
                as="li"
                staggerIndex={index + 1}
                className="rounded-jp-card border border-jp-border bg-jp-surface px-jp-md py-jp-lg text-center shadow-jp-card"
              >
                {item.value ? (
                  <p className="font-sans text-jp-h3 font-bold text-jp-primary">{item.value}</p>
                ) : null}
                {item.label ? <p className="mt-1 text-jp-sm font-medium text-jp-muted">{item.label}</p> : null}
              </ScrollReveal>
            ))}
          </ul>
        </PageContainer>
      </SectionContainer>
    </ScrollReveal>
  );
}
