import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { Badge } from "@/components/ui/Badge";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import Image from "next/image";
import { FEATURED_OFFER_FIXTURES } from "../fixtures/offers";

export function FeaturedOffersSection() {
  return (
    <SectionContainer className="bg-jp-surface-muted/50">
      <PageContainer>
        <h2 className="font-display text-jp-h2 font-bold text-jp-text">Featured offers</h2>
        <p className="mt-2 max-w-2xl text-jp-body text-jp-muted">
          Seasonal inspiration and route ideas — explore without live supplier pricing.
        </p>

        <div className="mt-jp-lg grid gap-4 md:grid-cols-3">
          {FEATURED_OFFER_FIXTURES.map((offer) => (
            <article
              key={offer.id}
              className="flex flex-col overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card"
            >
              <div className="relative aspect-[16/9] bg-jp-primary-soft">
                <Image src={offer.image} alt={offer.imageAlt} fill sizes="(max-width: 768px) 100vw, 33vw" className="object-cover" />
              </div>
              <div className="flex flex-1 flex-col p-jp-md">
                {offer.badge ? <Badge variant="new">{offer.badge}</Badge> : null}
                <h3 className="mt-2 font-sans text-jp-md font-semibold text-jp-text">{offer.title}</h3>
                <p className="mt-1 flex-1 text-jp-sm text-jp-muted">{offer.subtitle}</p>
                {offer.samplePrice ? (
                  <p className="mt-2 text-jp-xs text-jp-muted">{offer.samplePrice} (sample)</p>
                ) : null}
                <SecondaryButton className="mt-4 w-full">{offer.cta}</SecondaryButton>
              </div>
            </article>
          ))}
        </div>
      </PageContainer>
    </SectionContainer>
  );
}
