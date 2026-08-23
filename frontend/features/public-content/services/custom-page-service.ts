import { laravelApiPath } from "@/services/flight-search";
import type { PublicSeo } from "../types";
import { fetchWithTimeout } from "../utils/laravel-api";
import { splitParagraphs } from "../utils/content-mapper";

export type CustomClientPageSection = {
  id: string;
  heading?: string;
  eyebrow?: string;
  body?: string;
  type?: string;
};

export type CustomClientPage = {
  slug: string;
  title: string;
  subtitle?: string;
  sections: CustomClientPageSection[];
  seo: PublicSeo;
  source: "cms";
};

type LaravelCustomPageResponse = {
  slug: string;
  title: string;
  content: Record<string, unknown>;
  seo: PublicSeo;
};

function mapSections(content: Record<string, unknown>): CustomClientPageSection[] {
  const root = content.sections as { items?: Array<Record<string, string>> } | Array<Record<string, string>> | undefined;
  const items = Array.isArray(root) ? root : root?.items ?? [];

  return items
    .filter((section) => section.enabled !== "0")
    .map((section, index) => ({
      id: String(section.id ?? `section-${index}`),
      heading: section.heading ? String(section.heading) : undefined,
      eyebrow: section.eyebrow ? String(section.eyebrow) : undefined,
      body: section.body ? String(section.body) : undefined,
      type: section.type ? String(section.type) : "rich_text",
    }));
}

export const CustomPageService = {
  async getBySlug(slug: string): Promise<CustomClientPage | null> {
    try {
      const response = await fetchWithTimeout(
        laravelApiPath(`/api/public/content/custom/${encodeURIComponent(slug)}`),
        {
          headers: { Accept: "application/json" },
          cache: "no-store",
        },
      );
      if (response.status === 404) return null;
      if (!response.ok) return null;

      const body = (await response.json()) as LaravelCustomPageResponse;
      const identity = (body.content.identity ?? {}) as Record<string, string>;
      const sections = mapSections(body.content);

      return {
        slug: body.slug,
        title: body.title,
        subtitle: identity.subtitle,
        sections,
        seo: body.seo,
        source: "cms",
      };
    } catch {
      return null;
    }
  },
};

export function sectionParagraphs(section: CustomClientPageSection): string[] {
  return splitParagraphs(section.body ?? "");
}
