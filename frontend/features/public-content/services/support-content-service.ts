import { SUPPORT_PAGE_FIXTURE } from "../fixtures/support";
import type { ContentCard, PublicPageHero, PublicSeo, SupportPageContent } from "../types";
import { fetchManagedPage, mergeContactDetails } from "../utils/laravel-api";
import { allowContentFixtures } from "../utils/content-policy";
import { resolveDestination } from "../utils/content-mapper";

function mapHero(content: Record<string, unknown>): PublicPageHero {
  const hero = (content.hero ?? {}) as Record<string, string>;
  return {
    kicker: hero.kicker,
    title: hero.title || SUPPORT_PAGE_FIXTURE.hero.title,
    description: hero.description,
  };
}

function mapDepartments(content: Record<string, unknown>): ContentCard[] {
  const section = content.department_cards as { items?: Array<Record<string, string>> } | undefined;
  return (section?.items ?? [])
    .filter((item) => item.enabled !== "0")
    .map((item) => ({
      id: String(item.id ?? item.title ?? "dept"),
      title: String(item.title ?? ""),
      body: String(item.body ?? ""),
    }));
}

export const SupportContentService = {
  async getSupportPage(): Promise<SupportPageContent> {
    const remote = await fetchManagedPage("support");
    if (!remote || remote.source === "empty") {
      if (allowContentFixtures()) {
        return SUPPORT_PAGE_FIXTURE;
      }

      return {
        source: "empty",
        hero: { title: "Help and support" },
        topics: [],
        departments: [],
        contact: mergeContactDetails(null),
        seo: SUPPORT_PAGE_FIXTURE.seo,
      };
    }

    const content = remote.content;
    const departments = mapDepartments(content);
    const faqTeaser = (content.faq_teaser ?? {}) as Record<string, string>;

    return {
      source: "cms",
      hero: mapHero(content),
      topics: allowContentFixtures() ? SUPPORT_PAGE_FIXTURE.topics : [],
      departments: departments.length ? departments : allowContentFixtures() ? SUPPORT_PAGE_FIXTURE.departments : [],
      contact: mergeContactDetails(remote.contact),
      faqTeaser:
        faqTeaser.enabled === "1" && faqTeaser.title
          ? {
              title: faqTeaser.title,
              body: faqTeaser.body,
              linkLabel: faqTeaser.link_label || "View FAQ",
              linkHref: resolveDestination(faqTeaser.link_url || "route:faq"),
            }
          : allowContentFixtures() ? SUPPORT_PAGE_FIXTURE.faqTeaser : undefined,
      seo: { ...SUPPORT_PAGE_FIXTURE.seo, ...(remote.seo ?? {}) } as PublicSeo,
    };
  },
};
