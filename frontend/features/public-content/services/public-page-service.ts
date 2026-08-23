import { ABOUT_PAGE_FIXTURE } from "../fixtures/about";
import type { ContentCard, PublicPage, PublicPageHero, PublicSeo } from "../types";
import { fetchManagedPage } from "../utils/laravel-api";
import { allowContentFixtures, resolveContentSource } from "../utils/content-policy";
import { resolveDestination, splitListLines, splitParagraphs } from "../utils/content-mapper";

function mapHero(content: Record<string, unknown>): PublicPageHero {
  const hero = (content.hero ?? {}) as Record<string, string>;
  return {
    kicker: hero.kicker,
    title: hero.title || "About JetPakistan",
    description: hero.description,
  };
}

function mapSeo(seo: PublicSeo | undefined, fallback: PublicSeo): PublicSeo {
  if (!seo?.title) return fallback;
  return { ...fallback, ...seo };
}

function mapFeatureCards(content: Record<string, unknown>): ContentCard[] {
  const section = content.feature_cards as { items?: Array<Record<string, string>> } | undefined;
  return (section?.items ?? [])
    .filter((item) => item.enabled !== "0")
    .map((item) => ({
      id: item.id ?? item.title ?? "card",
      title: item.title ?? "",
      body: item.body ?? "",
    }));
}

function mapContentGrid(content: Record<string, unknown>): ContentCard[] {
  const section = content.content_grid as { items?: Array<Record<string, string>> } | undefined;
  return (section?.items ?? [])
    .filter((item) => item.enabled !== "0")
    .map((item) => ({
      id: item.id ?? item.title ?? "grid",
      title: item.title ?? "",
      body: item.body ?? "",
      format: item.format === "list" ? "list" : "paragraphs",
    }));
}

export const PublicPageService = {
  async getAboutPage(options?: { preview?: boolean; headers?: Record<string, string> }): Promise<PublicPage> {
    const remote = await fetchManagedPage("about", options);
    if (!remote || remote.source === "empty" || !remote.content?.hero) {
      if (allowContentFixtures()) {
        return ABOUT_PAGE_FIXTURE;
      }

      return {
        pageKey: "about",
        source: "empty",
        hero: { title: "About JetPakistan" },
        sections: [],
        seo: ABOUT_PAGE_FIXTURE.seo,
      };
    }

    const content = remote.content;
    const featureCards = mapFeatureCards(content);
    const gridItems = mapContentGrid(content);
    const cta = (content.cta ?? {}) as Record<string, string>;

    return {
      pageKey: "about",
      source: resolveContentSource(true),
      hero: mapHero(content),
      sections: [
        ...gridItems.map((item) => ({
          id: item.id,
          title: item.title,
          body: item.format === "list" ? undefined : item.body,
          items:
            item.format === "list"
              ? splitListLines(item.body).map((line, index) => ({
                  id: `${item.id}-${index}`,
                  title: line,
                  body: "",
                }))
              : splitParagraphs(item.body).map((paragraph, index) => ({
                  id: `${item.id}-p-${index}`,
                  title: "",
                  body: paragraph,
                })),
        })),
        ...(featureCards.length
          ? [
              {
                id: "features",
                title: "Highlights",
                items: featureCards,
              },
            ]
          : []),
      ],
      contact: remote.contact,
      cta: {
        primaryLabel: cta.primary_label,
        primaryHref: resolveDestination(cta.primary_url ?? ""),
        secondaryLabel: cta.secondary_label,
        secondaryHref: resolveDestination(cta.secondary_url ?? ""),
      },
      seo: mapSeo(remote.seo, ABOUT_PAGE_FIXTURE.seo),
    };
  },
};
