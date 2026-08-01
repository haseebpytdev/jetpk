import type { CmsPageTemplate } from "./block-types";

const REGISTERED_TEMPLATES = new Set<CmsPageTemplate>([
  "default-content",
  "hero-content",
  "landing",
  "faq",
  "contact",
  "policy",
  "destination",
  "offer",
  "article-index",
  "article-detail",
]);

const PAGE_KEY_MAP: Record<string, CmsPageTemplate> = {
  about: "hero-content",
  support: "hero-content",
  faq: "faq",
  terms: "policy",
  privacy: "policy",
  "booking-lookup": "hero-content",
  "group-search": "landing",
  "agent-registration": "default-content",
  home: "landing",
  contact: "contact",
};

const ROUTE_FAMILY_MAP: Record<string, CmsPageTemplate> = {
  legal: "policy",
};

const SLUG_MAP: Record<string, CmsPageTemplate> = {
  "refund-policy": "policy",
  "cookie-policy": "policy",
  "cancellation-policy": "policy",
  "booking-terms": "policy",
};

export function isRegisteredTemplate(value: string): value is CmsPageTemplate {
  return REGISTERED_TEMPLATES.has(value as CmsPageTemplate);
}

export function resolvePageTemplate(input: {
  template?: string;
  pageKey?: string;
  slug?: string;
  routeFamily?: string;
}): CmsPageTemplate {
  if (input.template && isRegisteredTemplate(input.template)) {
    return input.template;
  }

  if (input.pageKey && PAGE_KEY_MAP[input.pageKey]) {
    return PAGE_KEY_MAP[input.pageKey];
  }

  if (input.routeFamily && ROUTE_FAMILY_MAP[input.routeFamily]) {
    return ROUTE_FAMILY_MAP[input.routeFamily];
  }

  if (input.slug) {
    const normalized = input.slug.toLowerCase().trim();
    if (SLUG_MAP[normalized]) {
      return SLUG_MAP[normalized];
    }
    if (normalized.startsWith("destination-")) {
      return "destination";
    }
    if (normalized.startsWith("offer-")) {
      return "offer";
    }
    if (normalized.startsWith("articles/")) {
      return "article-detail";
    }
    if (normalized === "articles") {
      return "article-index";
    }
  }

  if (input.template && !isRegisteredTemplate(input.template)) {
    if (process.env.NODE_ENV !== "production") {
      console.warn(`[cms-theme-v2] Unknown template "${input.template}" — falling back to default-content`);
    }
  }

  return "default-content";
}

export function getRegisteredTemplates(): CmsPageTemplate[] {
  return [...REGISTERED_TEMPLATES];
}
