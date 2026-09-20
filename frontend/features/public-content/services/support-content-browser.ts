import { SUPPORT_PAGE_FIXTURE } from "../fixtures/support";
import type { ContentCard, PublicPageHero, PublicSeo, SupportPageContent, LaravelManagedPageResponse } from "../types";
import { fetchManagedPageBrowser } from "../utils/managed-page-browser";
import { mergeContactDetails } from "../utils/contact-merge";
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

export function resolveSupportPage(remote: LaravelManagedPageResponse | null): SupportPageContent {
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
        : allowContentFixtures()
          ? SUPPORT_PAGE_FIXTURE.faqTeaser
          : undefined,
    seo: { ...SUPPORT_PAGE_FIXTURE.seo, ...(remote.seo ?? {}) } as PublicSeo,
  };
}

/** Client soft-nav loader — no React cache. */
export async function loadSupportPageBrowser(): Promise<SupportPageContent> {
  return resolveSupportPage(await fetchManagedPageBrowser("support"));
}
