import { FAQ_PAGE_FIXTURE } from "../fixtures/faq";
import type { FaqCategory, FaqPageContent, PublicPageHero, PublicSeo } from "../types";
import { fetchManagedPage } from "../utils/laravel-api";
import { allowContentFixtures, resolveContentSource } from "../utils/content-policy";
import { resolveDestination } from "../utils/content-mapper";

function mapHero(content: Record<string, unknown>): PublicPageHero {
  const hero = (content.hero ?? {}) as Record<string, string>;
  return {
    kicker: hero.kicker,
    title: hero.title || FAQ_PAGE_FIXTURE.hero.title,
    description: hero.description,
  };
}

function mapCategories(content: Record<string, unknown>): FaqCategory[] {
  const root = content.categories as { items?: Array<Record<string, unknown>> } | undefined;
  return (root?.items ?? [])
    .filter((item) => item.enabled !== "0")
    .map((category) => {
      const id = String(category.id ?? category.title ?? "category");
      const questions = (category.questions as Array<Record<string, string>> | undefined) ?? [];
      return {
        id,
        title: String(category.title ?? ""),
        items: questions
          .filter((q) => q.enabled !== "0")
          .map((q) => ({
            id: String(q.id ?? q.question ?? "faq"),
            categoryId: id,
            question: String(q.question ?? ""),
            answer: String(q.answer ?? ""),
          })),
      };
    })
    .filter((category) => category.items.length > 0);
}

export const FaqService = {
  async getFaqPage(options?: { preview?: boolean; headers?: Record<string, string> }): Promise<FaqPageContent> {
    const remote = await fetchManagedPage("faq", options);
    if (!remote || remote.source === "empty") {
      if (allowContentFixtures()) {
        return FAQ_PAGE_FIXTURE;
      }

      return {
        source: "empty",
        hero: { title: "Frequently asked questions" },
        categories: [],
        seo: FAQ_PAGE_FIXTURE.seo,
      };
    }

    const content = remote.content;
    const categories = mapCategories(content);
    const cta = (content.cta ?? {}) as Record<string, string>;

    return {
      source: categories.length ? resolveContentSource(true) : allowContentFixtures() ? "fixture" : "empty",
      hero: mapHero(content),
      categories: categories.length ? categories : allowContentFixtures() ? FAQ_PAGE_FIXTURE.categories : [],
      cta: cta.label
        ? { label: cta.label, href: resolveDestination(cta.url ?? "/support") }
        : FAQ_PAGE_FIXTURE.cta,
      seo: { ...FAQ_PAGE_FIXTURE.seo, ...(remote.seo ?? {}) } as PublicSeo,
    };
  },
};
