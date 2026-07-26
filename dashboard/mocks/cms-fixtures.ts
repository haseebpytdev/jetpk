import { CMS_BRAND } from "@/types/cms";
import type {
  CmsAsset,
  CmsBanner,
  CmsFaqItem,
  CmsNotice,
  CmsPage,
  CmsRevision,
  CmsSectionInstance,
} from "@/types/cms";
import { mergeValidationIssues } from "@/features/cms/validation/link-validation";

const UPDATED = "2026-06-15T10:00:00Z";
const AUTHOR = "JP-STAFF-001";

function emptyValidation() {
  return mergeValidationIssues([]);
}

function assetVariant(w: number, h: number, label: string) {
  return { width: w, height: h, aspectRatio: `${w}:${h}`, placeholderLabel: label };
}

export const mockCmsAssets: CmsAsset[] = Array.from({ length: 40 }, (_, i) => {
  const id = `JP-CMS-AS-${String(i + 1).padStart(3, "0")}`;
  const categories = ["hero", "support", "offer", "destination", "airline", "campaign", "notice", "general"] as const;
  const category = categories[i % categories.length];
  const approved = i % 7 !== 0;
  return {
    id,
    internalName: `jetpk-${category}-${i + 1}`,
    category,
    desktop: assetVariant(1920, 1080, `Desktop placeholder ${i + 1}`),
    mobile: assetVariant(750, 940, `Mobile placeholder ${i + 1}`),
    dayVariant: i % 3 === 0 ? assetVariant(1920, 1080, `Day ${i + 1}`) : null,
    nightVariant: i % 3 === 0 ? assetVariant(1920, 1080, `Night ${i + 1}`) : null,
    fileType: "image/webp",
    altText: i % 11 === 0 ? "" : `JetPakistan ${category} visual ${i + 1}`,
    focalPointX: 0.5,
    focalPointY: 0.4,
    safeArea: "center",
    approvalStatus: approved ? "approved" : "unapproved",
    usageCount: (i % 5) + 1,
    createdDate: "2026-03-01",
    updatedDate: "2026-06-01",
    authorId: AUTHOR,
    validation: emptyValidation(),
  };
});

const PAGE_DEFS: { type: CmsPage["pageType"]; title: string; slug: string; sections: CmsSectionInstance["sectionType"][] }[] = [
  { type: "homepage", title: "Homepage", slug: "/", sections: ["homepage.hero", "homepage.flightSearchContext", "homepage.featuredOffers", "homepage.popularRoutes", "homepage.trustBenefits", "homepage.supportCallout", "homepage.faqPreview"] },
  { type: "about", title: "About Us", slug: "/about", sections: ["content.richText", "homepage.trustBenefits"] },
  { type: "contact", title: "Contact Us", slug: "/contact", sections: ["content.contactBlock", "homepage.supportCallout"] },
  { type: "faq", title: "FAQs", slug: "/faq", sections: ["content.faqCollection", "homepage.faqPreview"] },
  { type: "privacy", title: "Privacy Policy", slug: "/privacy", sections: ["content.policyPage"] },
  { type: "terms", title: "Terms and Conditions", slug: "/terms", sections: ["content.policyPage"] },
  { type: "refund", title: "Refund Policy", slug: "/refund", sections: ["content.policyPage"] },
  { type: "support", title: "Support", slug: "/support", sections: ["content.contactBlock", "homepage.supportCallout", "content.faqCollection"] },
  { type: "travel_guidance", title: "Travel Guidance", slug: "/travel-guidance", sections: ["content.richText"] },
  { type: "umrah", title: "Umrah Information", slug: "/umrah", sections: ["content.richText"] },
  { type: "airline_info", title: "Airline Information", slug: "/airlines", sections: ["homepage.airlineHighlights"] },
  { type: "destination_landing", title: "Dubai Destinations", slug: "/destinations/dubai", sections: ["content.destinationHero", "content.destinationHighlights"] },
  { type: "destination_landing", title: "Istanbul Destinations", slug: "/destinations/istanbul", sections: ["content.destinationHero", "content.destinationHighlights"] },
  { type: "campaign_landing", title: "Summer Travel Campaign", slug: "/campaigns/summer-2026", sections: ["homepage.hero", "homepage.featuredOffers", "global.promotionBanner"] },
  { type: "campaign_landing", title: "Umrah Season Campaign", slug: "/campaigns/umrah-2026", sections: ["homepage.hero", "global.promotionBanner"] },
  { type: "about", title: "Why JetPakistan", slug: "/about/why-jetpakistan", sections: ["content.richText", "homepage.trustBenefits"] },
  { type: "homepage", title: "Homepage Preview Draft", slug: "/preview/home", sections: ["homepage.hero", "global.noticeStrip"] },
  { type: "contact", title: "Agent Contact", slug: "/contact/agents", sections: ["content.contactBlock"] },
  { type: "faq", title: "Booking FAQs", slug: "/faq/booking", sections: ["content.faqCollection"] },
  { type: "support", title: "Travel Alerts", slug: "/support/alerts", sections: ["global.noticeStrip"] },
];

export const mockCmsSections: CmsSectionInstance[] = [];
export const mockCmsPages: CmsPage[] = PAGE_DEFS.map((def, pageIndex) => {
  const pageId = `JP-CMS-PG-${String(pageIndex + 1).padStart(3, "0")}`;
  const sectionIds: string[] = [];

  def.sections.forEach((sectionType, sectionIndex) => {
    const sectionId = `JP-CMS-SC-${String(mockCmsSections.length + 1).padStart(3, "0")}`;
    sectionIds.push(sectionId);
    mockCmsSections.push({
      id: sectionId,
      pageId,
      sectionType,
      variant: sectionType === "homepage.featuredOffers" ? "carousel" : "default",
      sortOrder: sectionIndex + 1,
      themeMode: sectionIndex % 2 === 0 ? "automatic" : "day",
      themeTreatment: "default",
      contentWidth: sectionType.includes("hero") ? "fullBleed" : "standard",
      spacing: "standard",
      textAlignment: sectionType.includes("hero") ? "start" : "center",
      deviceVisibility: "all",
      fields: {
        heading: `${def.title} — ${sectionType.split(".")[1] ?? "section"}`,
        title: def.title,
        altText: `JetPakistan ${sectionType} alt text`,
        searchRelationship: "embedded_below_hero",
      },
      assetIds: [mockCmsAssets[pageIndex % mockCmsAssets.length].id],
      publicationWindow: { startDate: "2026-06-01", endDate: null },
      validation: emptyValidation(),
    });
  });

  const statuses: CmsPage["status"][] = ["published", "published", "approved", "draft", "scheduled", "inReview"];
  return {
    id: pageId,
    brand: CMS_BRAND.id,
    pageType: def.type,
    title: def.title,
    slug: def.slug,
    locale: "en-PK",
    status: statuses[pageIndex % statuses.length],
    visibility: pageIndex === 16 ? "preview_only" : "public",
    seoTitle: `${def.title} | JetPakistan`,
    seoDescription: `JetPakistan ${def.title} — book flights with confidence.`,
    socialTitle: def.title,
    socialDescription: `Explore ${def.title} on JetPakistan.`,
    canonicalPath: def.slug,
    sectionIds,
    publicationWindow: { startDate: "2026-06-01", endDate: null },
    revisionNumber: (pageIndex % 5) + 1,
    lastUpdatedDate: UPDATED,
    updatedByUserId: AUTHOR,
    validation: emptyValidation(),
    previewAvailable: true,
  };
});

export const mockCmsBanners: CmsBanner[] = Array.from({ length: 24 }, (_, i) => {
  const families = ["hero", "support", "offer", "destination", "airline", "campaign", "promotion", "notice"] as const;
  const family = families[i % families.length];
  const asset = mockCmsAssets[i % mockCmsAssets.length];
  return {
    id: `JP-CMS-BN-${String(i + 1).padStart(3, "0")}`,
    family,
    title: `JetPakistan ${family} banner ${i + 1}`,
    subtitle: i % 2 === 0 ? "Preview promotional copy" : null,
    placements: family === "support" ? ["homepage_mid"] : ["homepage_top"],
    desktopAspectRatio: family === "support" ? "21:9" : "16:9",
    mobileAspectRatio: "16:9",
    supportsDayNight: family === "hero" || family === "support",
    desktopAssetId: asset.id,
    mobileAssetId: asset.id,
    dayAssetId: asset.dayVariant ? asset.id : null,
    nightAssetId: asset.nightVariant ? asset.id : null,
    altText: `JetPakistan ${family} banner alt ${i + 1}`,
    focalPointX: 0.5,
    focalPointY: 0.35,
    overlayAllowed: true,
    textAllowed: true,
    ctaAllowed: true,
    cta: {
      type: "internal_route",
      label: "Learn more",
      value: "/about",
    },
    priority: i + 1,
    publicationWindow: { startDate: "2026-06-01", endDate: "2026-12-31" },
    status: i % 4 === 0 ? "draft" : "published",
    validation: emptyValidation(),
  };
});

export const mockCmsNotices: CmsNotice[] = Array.from({ length: 24 }, (_, i) => {
  const severities = ["information", "success", "warning", "urgent", "maintenance", "airline_advisory", "visa_update", "service_interruption", "promotion"] as const;
  return {
    id: `JP-CMS-NT-${String(i + 1).padStart(3, "0")}`,
    title: `JetPakistan notice ${i + 1}`,
    message: `Synthetic advisory message ${i + 1} for dashboard preview.`,
    severity: severities[i % severities.length],
    placement: i % 2 === 0 ? "global_strip" : "homepage",
    audience: "all",
    startDate: "2026-06-01",
    endDate: i % 5 === 0 ? null : "2026-12-31",
    dismissible: i % 3 !== 0,
    cta: i % 4 === 0 ? { type: "internal_route", label: "View details", value: "/support" } : null,
    themeTreatment: "default",
    priority: i + 1,
    locale: "en-PK",
    status: i % 6 === 0 ? "draft" : "published",
    validation: emptyValidation(),
  };
});

export const mockCmsRevisions: CmsRevision[] = Array.from({ length: 48 }, (_, i) => {
  const entityTypes = ["page", "section", "banner", "notice", "asset"] as const;
  const entityType = entityTypes[i % entityTypes.length];
  const pools = {
    page: mockCmsPages,
    section: mockCmsSections,
    banner: mockCmsBanners,
    notice: mockCmsNotices,
    asset: mockCmsAssets,
  };
  const pool = pools[entityType];
  const entity = pool[i % pool.length];
  return {
    id: `JP-CMS-RV-${String(i + 1).padStart(3, "0")}`,
    entityType,
    entityId: entity.id,
    version: (i % 4) + 1,
    changeSummary: `Updated ${entityType} content for preview`,
    authorId: AUTHOR,
    timestamp: `2026-06-${String((i % 28) + 1).padStart(2, "0")}T12:00:00Z`,
    status: i % 3 === 0 ? "draft" : "published",
    validation: emptyValidation(),
  };
});

export const mockCmsFaqs: CmsFaqItem[] = Array.from({ length: 20 }, (_, i) => ({
  id: `JP-CMS-FQ-${String(i + 1).padStart(3, "0")}`,
  question: `How does JetPakistan handle booking question ${i + 1}?`,
  answer: `Structured answer ${i + 1} for preview — no raw HTML.`,
  category: i % 2 === 0 ? "Booking" : "Payments",
  displayOrder: i + 1,
  featured: i < 5,
  locale: "en-PK",
  assignedPageIds: [mockCmsPages[3].id, mockCmsPages[0].id],
  status: "published",
  revisionNumber: 1,
  validation: emptyValidation(),
}));

export const CMS_FIXTURE_COUNTS = {
  pages: mockCmsPages.length,
  sections: mockCmsSections.length,
  banners: mockCmsBanners.length,
  notices: mockCmsNotices.length,
  assets: mockCmsAssets.length,
  revisions: mockCmsRevisions.length,
};
