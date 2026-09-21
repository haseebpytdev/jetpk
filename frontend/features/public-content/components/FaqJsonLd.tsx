import { buildFaqPageJsonLd } from "../utils/faq-json-ld";
import type { FaqCategory } from "../types";

type FaqJsonLdProps = {
  categories: FaqCategory[];
};

/** Emits FAQPage JSON-LD when visible FAQ content exists. */
export function FaqJsonLd({ categories }: FaqJsonLdProps) {
  const payload = buildFaqPageJsonLd(categories);
  if (!payload) {
    return null;
  }

  return (
    <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(payload) }} />
  );
}
