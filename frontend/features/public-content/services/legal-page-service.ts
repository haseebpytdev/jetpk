import { PRIVACY_DOCUMENT_FIXTURE, TERMS_DOCUMENT_FIXTURE } from "../fixtures/legal";
import type { LegalDocument, LegalSection, PublicSeo } from "../types";
import { fetchManagedPage } from "../utils/laravel-api";
import { allowContentFixtures } from "../utils/content-policy";

function mapLegal(content: Record<string, unknown>, fallback: LegalDocument): LegalDocument {
  const legal = (content.legal ?? {}) as Record<string, unknown>;
  const sections = ((legal.sections as Array<Record<string, string>>) ?? [])
    .filter((section) => section.enabled !== "0")
    .map(
      (section): LegalSection => ({
        id: String(section.id ?? section.heading ?? "section"),
        heading: String(section.heading ?? ""),
        body: String(section.body ?? ""),
      }),
    );

  if (!legal.title && sections.length === 0) {
    return fallback;
  }

  return {
    source: "cms",
    title: String(legal.title ?? fallback.title),
    effectiveDate: legal.effective_date ? String(legal.effective_date) : fallback.effectiveDate,
    lastUpdated: legal.last_updated ? String(legal.last_updated) : fallback.lastUpdated,
    intro: legal.intro ? String(legal.intro) : fallback.intro,
    sections: sections.length ? sections : fallback.sections,
    seo: { ...fallback.seo, ...(content.seo as PublicSeo | undefined) } as PublicSeo,
  };
}

export const LegalPageService = {
  async getTerms(): Promise<LegalDocument> {
    const remote = await fetchManagedPage("terms");
    if (!remote || remote.source === "empty") {
      return allowContentFixtures() ? TERMS_DOCUMENT_FIXTURE : {
        source: "empty",
        title: "Terms and conditions",
        sections: [],
        seo: TERMS_DOCUMENT_FIXTURE.seo,
      };
    }
    return mapLegal(remote.content, TERMS_DOCUMENT_FIXTURE);
  },

  async getPrivacy(): Promise<LegalDocument> {
    const remote = await fetchManagedPage("privacy");
    if (!remote || remote.source === "empty") {
      return allowContentFixtures() ? PRIVACY_DOCUMENT_FIXTURE : {
        source: "empty",
        title: "Privacy policy",
        sections: [],
        seo: PRIVACY_DOCUMENT_FIXTURE.seo,
      };
    }
    return mapLegal(remote.content, PRIVACY_DOCUMENT_FIXTURE);
  },
};
