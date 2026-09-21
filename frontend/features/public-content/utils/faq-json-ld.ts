import type { FaqCategory } from "../types";

/**
 * Build FAQPage JSON-LD only from visible Q&A. Empty / fixture-less pages emit null
 * so we never advertise spammy empty FAQ schema.
 */
export function buildFaqPageJsonLd(categories: FaqCategory[]): Record<string, unknown> | null {
  const entities = categories.flatMap((category) =>
    category.items
      .filter((item) => item.question.trim() && item.answer.trim())
      .map((item) => ({
        "@type": "Question",
        name: item.question.trim(),
        acceptedAnswer: {
          "@type": "Answer",
          text: item.answer.trim(),
        },
      })),
  );

  if (entities.length === 0) {
    return null;
  }

  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: entities,
  };
}
