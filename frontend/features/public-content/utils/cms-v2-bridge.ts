import type { CmsPagePayload } from "@/features/cms-theme-v2";
import { resolvePageTemplate, sanitizeCmsHtml } from "@/features/cms-theme-v2";
import type { CmsPublicPage } from "../types";
import type { CustomClientPage } from "../services/custom-page-service";

export function cmsPublicPageToPayload(page: CmsPublicPage): CmsPagePayload {
  const blocks: CmsPagePayload["blocks"] = [];

  if (page.title || page.subtitle) {
    blocks.push({
      type: "hero",
      heading: page.title,
      body: page.subtitle,
    });
  }

  if (page.bodyHtml) {
    blocks.push({
      type: "richText",
      html: sanitizeCmsHtml(page.bodyHtml),
    });
  }

  return {
    title: page.title,
    slug: page.slug,
    template: resolvePageTemplate({ slug: page.slug }),
    blocks,
  };
}

export function customClientPageToPayload(page: CustomClientPage): CmsPagePayload {
  const blocks: CmsPagePayload["blocks"] = [];

  if (page.title || page.subtitle) {
    blocks.push({
      type: "hero",
      heading: page.title,
      body: page.subtitle,
    });
  }

  for (const section of page.sections) {
    if (section.heading && section.body) {
      blocks.push({
        type: "section",
        heading: section.heading,
        body: section.body,
      });
    } else if (section.body) {
      blocks.push({
        type: "richText",
        html: sanitizeCmsHtml(section.body),
      });
    }
  }

  return {
    title: page.title,
    slug: page.slug,
    template: resolvePageTemplate({ slug: page.slug }),
    blocks,
  };
}
