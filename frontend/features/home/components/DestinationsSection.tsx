"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { Badge } from "@/components/ui/Badge";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import Image from "next/image";
import { useRef } from "react";
import { DESTINATION_FIXTURES } from "../fixtures/destinations";

export function DestinationsSection() {
  const scrollerRef = useRef<HTMLDivElement>(null);

  const scrollBy = (direction: -1 | 1) => {
    const node = scrollerRef.current;
    if (!node) return;
    const amount = Math.max(node.clientWidth * 0.8, 280);
    node.scrollBy({ left: direction * amount, behavior: "smooth" });
  };

  return (
    <SectionContainer>
      <PageContainer>
        <div className="flex items-end justify-between gap-4">
          <div>
            <h2 className="font-display text-jp-h2 font-bold text-jp-text">Destinations on the Rise</h2>
            <p className="mt-2 max-w-2xl text-jp-body text-jp-muted">
              Popular routes from Pakistan — sample inspiration only, not live fares.
            </p>
          </div>
          <div className="hidden gap-2 sm:flex">
            <SecondaryButton type="button" aria-label="Scroll destinations left" onClick={() => scrollBy(-1)}>
              ←
            </SecondaryButton>
            <SecondaryButton type="button" aria-label="Scroll destinations right" onClick={() => scrollBy(1)}>
              →
            </SecondaryButton>
          </div>
        </div>

        <div
          ref={scrollerRef}
          className="mt-jp-lg flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          role="region"
          aria-label="Destination cards"
        >
          {DESTINATION_FIXTURES.map((destination) => (
            <article
              key={destination.id}
              className="w-[min(85vw,17rem)] shrink-0 snap-start overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card"
            >
              <div className="relative aspect-[4/3] bg-jp-surface-muted">
                <Image
                  src={destination.image}
                  alt={destination.imageAlt}
                  fill
                  sizes="(max-width: 768px) 85vw, 272px"
                  className="object-cover"
                />
              </div>
              <div className="p-jp-md">
                <Badge variant="new">{destination.label}</Badge>
                <h3 className="mt-2 font-display text-jp-md font-semibold text-jp-text">
                  {destination.city}
                </h3>
                <p className="text-jp-sm text-jp-muted">{destination.country}</p>
              </div>
            </article>
          ))}
        </div>
      </PageContainer>
    </SectionContainer>
  );
}
