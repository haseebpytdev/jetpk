"use client";

import { useState } from "react";
import { getSectionDefinition, requiresOfferCarousel } from "@/features/cms/registry/section-registry";
import { mockCmsAssets } from "@/mocks/cms-fixtures";
import type { CmsLink, CmsPreviewMode, CmsSectionInstance } from "@/types/cms";

type Props = {
  section: CmsSectionInstance;
  mode: CmsPreviewMode;
  compact?: boolean;
};

const OFFER_FIXTURES = [
  { origin: "KHI", destination: "DXB", price: "From PKR 45,000", label: "Karachi to Dubai" },
  { origin: "LHE", destination: "IST", price: "From PKR 52,000", label: "Lahore to Istanbul" },
  { origin: "ISB", destination: "JED", price: "From PKR 68,000", label: "Islamabad to Jeddah" },
  { origin: "KHI", destination: "LHR", price: "From PKR 125,000", label: "Karachi to London" },
];

export function CmsSectionPreview({ section, mode, compact = false }: Props) {
  const def = getSectionDefinition(section.sectionType);
  const heading = String(section.fields.heading ?? section.fields.title ?? def?.label ?? "");
  const supporting = String(section.fields.supportingText ?? section.fields.helperText ?? "");
  const eyebrow = String(section.fields.eyebrow ?? "");
  const primaryCta = section.fields.primaryCta as CmsLink | null | undefined;

  switch (section.sectionType) {
    case "homepage.hero":
    case "content.destinationHero":
      return <HeroPreview heading={heading} eyebrow={eyebrow} supporting={supporting} cta={primaryCta} section={section} mode={mode} compact={compact} />;
    case "homepage.flightSearchContext":
      return <FlightSearchContextPreview heading={heading} supporting={supporting} />;
    case "homepage.featuredOffers":
      return <FeaturedOffersPreview heading={heading} variant={section.variant} />;
    case "homepage.popularRoutes":
      return <PopularRoutesPreview heading={heading} />;
    case "homepage.featuredDestinations":
    case "content.destinationHighlights":
      return <DestinationsPreview heading={heading} />;
    case "homepage.trustBenefits":
      return <TrustBenefitsPreview heading={heading} />;
    case "homepage.supportCallout":
      return <SupportCalloutPreview heading={heading} supporting={supporting} cta={primaryCta} section={section} mode={mode} />;
    case "homepage.faqPreview":
    case "content.faqCollection":
      return <FaqPreview heading={heading} />;
    case "global.noticeStrip":
      return <NoticeStripPreview message={String(section.fields.message ?? heading)} />;
    case "content.richText":
    case "content.policyPage":
      return <RichTextPreview heading={heading} body={String(section.fields.body ?? supporting)} />;
    default:
      return (
        <div className="rounded-lg border border-jp-border p-4 text-sm">
          <p className="font-medium">{heading}</p>
          {supporting ? <p className="mt-1 text-jp-muted">{supporting}</p> : null}
          <p className="mt-2 font-mono text-xs text-jp-muted">{def?.frontendComponentKey}</p>
        </div>
      );
  }
}

function HeroPreview({
  heading,
  eyebrow,
  supporting,
  cta,
  section,
  mode,
  compact,
}: {
  heading: string;
  eyebrow: string;
  supporting: string;
  cta?: CmsLink | null;
  section: CmsSectionInstance;
  mode: CmsPreviewMode;
  compact?: boolean;
}) {
  const asset = mockCmsAssets.find((a) => section.assetIds.includes(a.id));
  const isNight = mode.includes("night");
  const isMobile = mode.includes("mobile");

  return (
    <div className={`relative overflow-hidden rounded-xl ${compact ? "min-h-[120px]" : "min-h-[200px]"} ${isNight ? "bg-gray-800" : "bg-gradient-to-br from-emerald-600 to-emerald-800"} text-white`} data-testid="cms-hero-preview">
      <div className="absolute inset-0 opacity-30" aria-hidden>
        <div className="flex h-full items-center justify-center text-xs">{asset?.desktop.placeholderLabel ?? "Asset placeholder"}</div>
      </div>
      <div className={`relative p-4 ${isMobile ? "text-sm" : ""}`}>
        {eyebrow ? <p className="text-xs uppercase tracking-wide opacity-80">{eyebrow}</p> : null}
        <h4 className={`font-bold ${compact ? "text-lg" : "text-2xl"}`}>{heading}</h4>
        {supporting ? <p className="mt-1 opacity-90">{supporting}</p> : null}
        {cta ? <span className="mt-3 inline-block rounded-lg bg-white px-3 py-1 text-sm font-medium text-emerald-800">{cta.label}</span> : null}
        {!asset?.altText.trim() ? <p className="mt-2 text-xs text-amber-200">Missing alt text</p> : null}
      </div>
    </div>
  );
}

function FlightSearchContextPreview({ heading, supporting }: { heading: string; supporting: string }) {
  return (
    <div data-testid="cms-flight-search-context">
      {heading ? <p className="mb-2 font-medium">{heading}</p> : null}
      {supporting ? <p className="mb-3 text-sm text-jp-muted">{supporting}</p> : null}
      <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600" aria-label="Flight search placeholder">
        Flight search component (non-interactive) — search logic is not CMS-controlled.
      </div>
    </div>
  );
}

function FeaturedOffersPreview({ heading, variant }: { heading: string; variant: string }) {
  const offers = OFFER_FIXTURES;
  const needsCarousel = requiresOfferCarousel(offers.length);
  const [index, setIndex] = useState(0);
  const visible = needsCarousel || variant === "carousel" ? offers.slice(index, index + 3) : offers.slice(0, 3);

  return (
    <div data-testid="cms-featured-offers-preview">
      <h4 className="font-semibold">{heading}</h4>
      <p className="text-xs text-jp-muted">Indicative pricing — static preview fares only.</p>
      {needsCarousel ? (
        <div className="mt-3">
          <div className="flex items-center gap-2">
            <button type="button" aria-label="Previous offers" className="min-h-11 rounded-lg border px-3" onClick={() => setIndex((i) => Math.max(0, i - 1))} disabled={index === 0}>‹</button>
            <div className="grid flex-1 gap-2 sm:grid-cols-3" role="list" aria-label="Featured offers carousel">
              {visible.map((offer) => (
                <OfferCard key={offer.label} offer={offer} />
              ))}
            </div>
            <button type="button" aria-label="Next offers" className="min-h-11 rounded-lg border px-3" onClick={() => setIndex((i) => Math.min(offers.length - 3, i + 1))} disabled={index >= offers.length - 3}>›</button>
          </div>
        </div>
      ) : (
        <div className="mt-3 grid gap-2 sm:grid-cols-3" role="list">
          {visible.map((offer) => (
            <OfferCard key={offer.label} offer={offer} />
          ))}
        </div>
      )}
    </div>
  );
}

function OfferCard({ offer }: { offer: (typeof OFFER_FIXTURES)[number] }) {
  return (
    <div className="rounded-xl border border-jp-border p-3 text-sm" role="listitem" aria-label={`${offer.label} indicative offer`}>
      <p className="font-medium">{offer.label}</p>
      <p className="text-xs text-jp-muted">{offer.origin} → {offer.destination}</p>
      <p className="mt-1 font-semibold">{offer.price}</p>
      <p className="text-xs text-jp-muted">Static preview fare</p>
    </div>
  );
}

function PopularRoutesPreview({ heading }: { heading: string }) {
  return (
    <div>
      <h4 className="font-semibold">{heading}</h4>
      <ul className="mt-2 space-y-2 text-sm">
        {["KHI → DXB", "LHE → IST", "ISB → JED"].map((route) => (
          <li key={route} className="flex justify-between rounded-lg border border-jp-border px-3 py-2">
            <span>{route}</span>
            <span className="text-jp-muted">Indicative</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function DestinationsPreview({ heading }: { heading: string }) {
  return (
    <div>
      <h4 className="font-semibold">{heading}</h4>
      <div className="mt-2 grid gap-2 sm:grid-cols-2">
        {["Dubai", "Istanbul"].map((dest) => (
          <div key={dest} className="rounded-xl border border-jp-border p-3 text-sm">
            <p className="font-medium">{dest}</p>
            <p className="text-xs text-jp-muted">Destination highlight preview</p>
          </div>
        ))}
      </div>
    </div>
  );
}

function TrustBenefitsPreview({ heading }: { heading: string }) {
  const icons = ["shield-check", "headset", "ticket"];
  return (
    <div>
      <h4 className="font-semibold">{heading}</h4>
      <ul className="mt-2 grid gap-2 sm:grid-cols-3">
        {icons.map((icon) => (
          <li key={icon} className="rounded-lg border border-jp-border p-3 text-sm">
            <span className="font-mono text-xs">{icon}</span>
            <p className="mt-1">Approved icon key</p>
          </li>
        ))}
      </ul>
    </div>
  );
}

function SupportCalloutPreview({
  heading,
  supporting,
  cta,
  section,
  mode,
}: {
  heading: string;
  supporting: string;
  cta?: CmsLink | null;
  section: CmsSectionInstance;
  mode: CmsPreviewMode;
}) {
  const isMobile = mode.includes("mobile");
  return (
    <div className={`relative overflow-hidden rounded-xl bg-gradient-to-r from-emerald-700 to-emerald-900 text-white ${isMobile ? "min-h-[120px]" : "aspect-[21/9] min-h-[140px]"}`} data-testid="cms-support-callout-preview">
      <div className="flex h-full flex-col justify-center p-4">
        <h4 className="text-lg font-semibold">{heading}</h4>
        {supporting ? <p className="mt-1 text-sm opacity-90">{supporting}</p> : null}
        {cta ? <span className="mt-2 inline-block w-fit rounded-lg bg-white px-3 py-1 text-sm font-medium text-emerald-800">{cta.label}</span> : null}
      </div>
    </div>
  );
}

function FaqPreview({ heading }: { heading: string }) {
  const [open, setOpen] = useState<string | null>("q1");
  const items = [
    { id: "q1", q: "How do I book a flight?", a: "Search for flights on the homepage and complete checkout." },
    { id: "q2", q: "What payment methods are accepted?", a: "JetPakistan supports card and bank transfer in preview." },
  ];
  return (
    <div>
      <h4 className="font-semibold">{heading}</h4>
      <div className="mt-2 space-y-2">
        {items.map((item) => (
          <div key={item.id} className="rounded-lg border border-jp-border">
            <button
              type="button"
              className="flex w-full items-center justify-between px-3 py-2 text-left text-sm font-medium"
              aria-expanded={open === item.id}
              aria-controls={`faq-panel-${item.id}`}
              onClick={() => setOpen(open === item.id ? null : item.id)}
            >
              {item.q}
              <span aria-hidden>{open === item.id ? "−" : "+"}</span>
            </button>
            {open === item.id ? (
              <div id={`faq-panel-${item.id}`} className="border-t border-jp-border px-3 py-2 text-sm text-jp-muted">
                {item.a}
              </div>
            ) : null}
          </div>
        ))}
      </div>
    </div>
  );
}

function NoticeStripPreview({ message }: { message: string }) {
  return (
    <div className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
      {message}
    </div>
  );
}

function RichTextPreview({ heading, body }: { heading: string; body: string }) {
  return (
    <div className="space-y-2 text-sm">
      <h4 className="text-lg font-semibold">{heading}</h4>
      <p>{body}</p>
    </div>
  );
}
