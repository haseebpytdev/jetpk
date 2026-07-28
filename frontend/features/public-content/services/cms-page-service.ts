import { laravelApiPath } from "@/services/flight-search";
import { fetchWithTimeout } from "../utils/laravel-api";
import type { CmsPublicPage, PublicSeo } from "../types";
import { isTrustedCmsHtml } from "../utils/sanitize";

type LaravelCmsResponse = {
  slug: string;
  title: string;
  subtitle?: string;
  body_html: string;
  seo: PublicSeo;
};

export const CmsPageService = {
  async getBySlug(slug: string): Promise<CmsPublicPage | null> {
    try {
      const response = await fetchWithTimeout(laravelApiPath(`/api/public/content/cms/${encodeURIComponent(slug)}`), {
        headers: { Accept: "application/json" },
        next: { revalidate: 60 },
      });
      if (response.status === 404) return null;
      if (!response.ok) return null;
      const body = (await response.json()) as LaravelCmsResponse;
      if (!isTrustedCmsHtml(body.body_html)) return null;
      return {
        slug: body.slug,
        title: body.title,
        subtitle: body.subtitle,
        bodyHtml: body.body_html,
        seo: body.seo,
        source: "cms",
      };
    } catch {
      return null;
    }
  },
};
