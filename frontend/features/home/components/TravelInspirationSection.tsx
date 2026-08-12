import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { Badge } from "@/components/ui/Badge";
import Image from "next/image";
import { INSPIRATION_FIXTURES } from "../fixtures/inspiration";

export function TravelInspirationSection() {
  return (
    <SectionContainer>
      <PageContainer>
        <h2 className="font-display text-jp-h2 font-bold text-jp-text">Travel inspiration</h2>
        <p className="mt-2 max-w-2xl text-jp-body text-jp-muted">
          Guides and ideas to help you plan your next journey.
        </p>

        <div className="mt-jp-lg grid gap-4 md:grid-cols-3">
          {INSPIRATION_FIXTURES.map((card) => (
            <article
              key={card.id}
              className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card"
            >
              <div className="relative aspect-[16/10] bg-jp-surface-muted">
                <Image src={card.image} alt={card.imageAlt} fill sizes="(max-width: 768px) 100vw, 33vw" className="object-cover" />
              </div>
              <div className="p-jp-md">
                <Badge variant="new">{card.category}</Badge>
                <h3 className="mt-2 font-sans text-jp-md font-semibold text-jp-text">{card.title}</h3>
                <p className="mt-2 text-jp-sm leading-relaxed text-jp-muted">{card.excerpt}</p>
              </div>
            </article>
          ))}
        </div>
      </PageContainer>
    </SectionContainer>
  );
}
