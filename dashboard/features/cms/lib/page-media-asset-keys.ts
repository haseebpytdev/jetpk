/**
 * Maps CMS page editor section picks to ClientPageMediaSchema asset keys.
 * Keep in sync with app/Support/Client/ClientPageMediaSchema.php.
 */
const PAGE_SECTION_ASSET_KEYS: Record<string, Record<string, string>> = {
  login: {
    hero: "auth_illustration",
    auth: "auth_illustration",
  },
  register: {
    hero: "auth_illustration",
    auth: "auth_illustration",
  },
  "booking-lookup": {
    hero: "booking_lookup_hero",
    lookup_hero: "booking_lookup_hero",
  },
};

export function resolvePageMediaAssetKey(pageKey: string, sectionKey: string): string {
  const pageMap = PAGE_SECTION_ASSET_KEYS[pageKey];
  if (pageMap?.[sectionKey]) {
    return pageMap[sectionKey];
  }

  if (sectionKey === "hero") {
    return "hero_background";
  }

  return `${sectionKey}_image`;
}

export function mediaSectionKeysForPage(pageKey: string): string[] {
  const base = ["hero", "support_cta", "seo"];
  if (pageKey === "login" || pageKey === "register") {
    return [...base, "auth"];
  }
  if (pageKey === "booking-lookup") {
    return [...base, "lookup_hero"];
  }
  return base;
}

export function mediaSectionLabel(sectionKey: string, sections: Array<{ key: string; label: string }>): string {
  if (sectionKey === "auth") return "Auth illustration";
  if (sectionKey === "lookup_hero") return "Lookup hero";
  if (sectionKey === "seo") return "SEO";
  return sections.find((s) => s.key === sectionKey)?.label ?? sectionKey;
}
