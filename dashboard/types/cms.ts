import { CMS_BRAND_ID, CMS_BRAND_LABEL } from "@/lib/reports/constants";

export type CmsBrand = typeof CMS_BRAND_ID;

export const CMS_BRAND: { id: CmsBrand; label: typeof CMS_BRAND_LABEL } = {
  id: CMS_BRAND_ID,
  label: CMS_BRAND_LABEL,
};

export type CmsLocale = "en-PK" | "ur-PK";

export type CmsPageType =
  | "homepage"
  | "about"
  | "contact"
  | "faq"
  | "privacy"
  | "terms"
  | "refund"
  | "support"
  | "travel_guidance"
  | "umrah"
  | "airline_info"
  | "destination_landing"
  | "campaign_landing";

export type CmsPageStatus =
  | "draft"
  | "inReview"
  | "approved"
  | "scheduled"
  | "published"
  | "expired"
  | "archived";

export type CmsThemeMode = "automatic" | "day" | "night" | "dualAsset" | "neutral";

export type CmsThemeTreatment = "default" | "brand" | "muted" | "elevated" | "imageOverlay" | "transparent";

export type CmsSurface = "page" | "section" | "banner" | "notice";

export type CmsContentWidth = "narrow" | "standard" | "wide" | "fullBleed";

export type CmsSpacing = "compact" | "standard" | "spacious";

export type CmsTextAlignment = "start" | "center" | "end";

export type CmsDeviceVisibility = "all" | "desktop" | "mobile" | "tablet";

export type CmsPublicationWindow = {
  startDate: string | null;
  endDate: string | null;
};

export type CmsValidationSeverity = "error" | "warning" | "info";

export type CmsValidationIssue = {
  severity: CmsValidationSeverity;
  code: string;
  message: string;
  fieldPath: string;
  recordId: string;
  suggestedResolution: string;
  blocking: boolean;
};

export type CmsValidationResult = {
  valid: boolean;
  issues: CmsValidationIssue[];
};

export type CmsLinkType =
  | "internal_route"
  | "external_url"
  | "search_preset"
  | "support_contact"
  | "whatsapp_action"
  | "none";

export type CmsLink = {
  type: CmsLinkType;
  label: string;
  value: string;
  requiresReview?: boolean;
  searchPreset?: {
    origin?: string;
    destination?: string;
    tripType?: "one_way" | "return";
  };
};

export type CmsAssetApprovalStatus = "approved" | "pending" | "rejected" | "unapproved";

export type CmsAssetVariant = {
  width: number;
  height: number;
  aspectRatio: string;
  placeholderLabel: string;
};

export type CmsAsset = {
  id: string;
  internalName: string;
  category: "hero" | "support" | "offer" | "destination" | "airline" | "campaign" | "notice" | "general";
  desktop: CmsAssetVariant;
  mobile: CmsAssetVariant;
  dayVariant: CmsAssetVariant | null;
  nightVariant: CmsAssetVariant | null;
  fileType: "image/webp" | "image/jpeg" | "image/png";
  altText: string;
  focalPointX: number;
  focalPointY: number;
  safeArea: string;
  approvalStatus: CmsAssetApprovalStatus;
  usageCount: number;
  createdDate: string;
  updatedDate: string;
  authorId: string;
  validation: CmsValidationResult;
};

export type CmsAssetUsage = {
  assetId: string;
  entityType: "page" | "section" | "banner" | "notice";
  entityId: string;
  variant: "desktop" | "mobile" | "day" | "night";
};

export type CmsSectionType =
  | "homepage.hero"
  | "homepage.flightSearchContext"
  | "homepage.featuredOffers"
  | "homepage.popularRoutes"
  | "homepage.featuredDestinations"
  | "homepage.airlineHighlights"
  | "homepage.trustBenefits"
  | "homepage.supportCallout"
  | "homepage.faqPreview"
  | "homepage.newsletterCallout"
  | "global.noticeStrip"
  | "global.promotionBanner"
  | "content.richText"
  | "content.policyPage"
  | "content.contactBlock"
  | "content.faqCollection"
  | "content.destinationHero"
  | "content.destinationHighlights";

export type CmsSectionVariant = "default" | "compact" | "carousel" | "grid" | "banner" | "overlay";

export type CmsSectionFieldDefinition = {
  key: string;
  label: string;
  fieldType: "text" | "richText" | "link" | "asset" | "number" | "boolean" | "enum";
  required: boolean;
  maxLength?: number;
  enumValues?: string[];
};

export type CmsSectionFieldValue = string | number | boolean | CmsLink | null;

export type CmsSectionDefinition = {
  sectionType: CmsSectionType;
  label: string;
  frontendComponentKey: string;
  supportedPageTypes: CmsPageType[];
  supportedVariants: CmsSectionVariant[];
  supportedThemeModes: CmsThemeMode[];
  supportedDeviceModes: CmsDeviceVisibility[];
  maxPerPage: number | null;
  requiredFields: string[];
  optionalFields: string[];
  assetRequirements: string[];
  aspectRatioRequirements: { desktop?: string; mobile?: string };
  validationRules: string[];
  previewCapable: boolean;
  apiMappingNotes: string;
  functionalBoundaryNotes: string;
};

export type CmsSectionInstance = {
  id: string;
  pageId: string;
  sectionType: CmsSectionType;
  variant: CmsSectionVariant;
  sortOrder: number;
  themeMode: CmsThemeMode;
  themeTreatment: CmsThemeTreatment;
  contentWidth: CmsContentWidth;
  spacing: CmsSpacing;
  textAlignment: CmsTextAlignment;
  deviceVisibility: CmsDeviceVisibility;
  fields: Record<string, CmsSectionFieldValue>;
  assetIds: string[];
  publicationWindow: CmsPublicationWindow;
  validation: CmsValidationResult;
};

export type CmsPage = {
  id: string;
  internalId?: string;
  brand: CmsBrand;
  pageType: CmsPageType;
  title: string;
  slug: string;
  locale: CmsLocale;
  status: CmsPageStatus;
  visibility: "public" | "hidden" | "preview_only";
  content?: string;
  excerpt?: string | null;
  seoTitle: string;
  seoDescription: string;
  socialTitle: string;
  socialDescription: string;
  canonicalPath: string;
  robots?: string;
  showInFooter?: boolean;
  footerGroup?: string | null;
  footerLabel?: string | null;
  sectionIds: string[];
  publicationWindow: CmsPublicationWindow;
  revisionNumber: number;
  lastUpdatedDate: string;
  updatedByUserId: string;
  validation: CmsValidationResult;
  previewAvailable: boolean;
};

export type CmsNoticeSeverity =
  | "information"
  | "success"
  | "warning"
  | "urgent"
  | "maintenance"
  | "airline_advisory"
  | "visa_update"
  | "service_interruption"
  | "promotion";

export type CmsNotice = {
  id: string;
  title: string;
  message: string;
  severity: CmsNoticeSeverity;
  placement: "global_strip" | "homepage" | "checkout" | "support";
  audience: "all" | "guest" | "agent";
  startDate: string;
  endDate: string | null;
  dismissible: boolean;
  cta: CmsLink | null;
  themeTreatment: CmsThemeTreatment;
  priority: number;
  locale: CmsLocale;
  status: CmsPageStatus;
  validation: CmsValidationResult;
};

export type CmsBannerFamily =
  | "hero"
  | "support"
  | "offer"
  | "destination"
  | "airline"
  | "campaign"
  | "promotion"
  | "notice";

export type CmsBanner = {
  id: string;
  family: CmsBannerFamily;
  title: string;
  subtitle: string | null;
  placements: string[];
  desktopAspectRatio: string;
  mobileAspectRatio: string;
  supportsDayNight: boolean;
  desktopAssetId: string | null;
  mobileAssetId: string | null;
  dayAssetId: string | null;
  nightAssetId: string | null;
  altText: string;
  focalPointX: number;
  focalPointY: number;
  overlayAllowed: boolean;
  textAllowed: boolean;
  ctaAllowed: boolean;
  cta: CmsLink | null;
  priority: number;
  publicationWindow: CmsPublicationWindow;
  status: CmsPageStatus;
  validation: CmsValidationResult;
};

export type CmsPreviewMode =
  | "desktop_day"
  | "desktop_night"
  | "tablet"
  | "mobile_day"
  | "mobile_night";

export type CmsPreviewContract = {
  mode: CmsPreviewMode;
  viewportWidth: number;
  themeMode: CmsThemeMode;
  label: string;
  dashboardPreviewOnly: true;
};

export type CmsComponentContract = {
  frontendComponentKey: string;
  sectionType: CmsSectionType;
  allowedFields: string[];
  prohibitedControls: string[];
  themeTokens: string[];
};

export type CmsFrontendMapping = {
  frontendComponentKey: string;
  futureNextJsComponent: string;
  notes: string;
};

export type CmsRevision = {
  id: string;
  entityType: "page" | "section" | "banner" | "notice" | "asset";
  entityId: string;
  version: number;
  changeSummary: string;
  authorId: string;
  timestamp: string;
  status: CmsPageStatus;
  validation: CmsValidationResult;
};

export type CmsFaqItem = {
  id: string;
  question: string;
  answer: string;
  category: string;
  displayOrder: number;
  featured: boolean;
  locale: CmsLocale;
  assignedPageIds: string[];
  status: CmsPageStatus;
  revisionNumber: number;
  validation: CmsValidationResult;
};

export type CmsContentState = "loading" | "empty" | "error" | "ready";

export type CmsQuery = {
  status: CmsPageStatus | "all";
  pageType: CmsPageType | "all";
  sectionType: CmsSectionType | "all";
  themeMode: CmsThemeMode | "all";
  locale: CmsLocale | "all";
  assetStatus: CmsAssetApprovalStatus | "all";
  bannerFamily: CmsBannerFamily | "all";
  noticeSeverity: CmsNoticeSeverity | "all";
  validationState: "valid" | "warning" | "blocked" | "all";
  audience: "all" | "guest" | "agent";
  placement: string;
  search: string;
  page: number;
  pageSize: number;
  sort: string;
  direction: "asc" | "desc";
  selected: string | null;
  previewMode: CmsPreviewMode;
  previewError: boolean;
  previewLoading: boolean;
  previewEmpty: boolean;
};

export type CmsMetric = {
  key: string;
  label: string;
  value: number;
  description?: string;
};

export type CmsDistributionSegment = {
  key: string;
  label: string;
  value: number;
};

export type CmsAttentionItem = {
  id: string;
  category: string;
  categoryLabel: string;
  title: string;
  description: string;
  href: string;
  linkLabel: string;
  recordId: string;
};

export type CmsTableColumn = {
  key: string;
  label: string;
  sortable?: boolean;
  align?: "start" | "end";
};

export type CmsTableRow = Record<string, string | number | boolean | null | undefined> & {
  id: string;
  href?: string;
};

export type CmsModuleTable = {
  columns: CmsTableColumn[];
  rows: CmsTableRow[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
};

export type CmsFacets = {
  pageTypes: string[];
  sectionTypes: string[];
  statuses: string[];
  themeModes: string[];
  locales: string[];
  bannerFamilies: string[];
  noticeSeverities: string[];
  assetStatuses: string[];
  placements: string[];
  audiences: string[];
};

export type CmsModuleResult = {
  state: CmsContentState;
  module: CmsModuleKey;
  query: CmsQuery;
  brand: typeof CMS_BRAND;
  metrics: CmsMetric[];
  validationSummary: CmsFoundationResult["validationSummary"];
  distributions: {
    publication: CmsDistributionSegment[];
    contentType: CmsDistributionSegment[];
    validation: CmsDistributionSegment[];
    theme: CmsDistributionSegment[];
    assets: CmsDistributionSegment[];
  };
  attentionQueue: CmsAttentionItem[];
  recentRevisions: CmsRevision[];
  scheduledQueue: { id: string; title: string; type: string; startDate: string; href: string }[];
  reviewQueue: { id: string; title: string; reason: string; href: string }[];
  table: CmsModuleTable;
  selectedPage: CmsPage | null;
  selectedSection: CmsSectionInstance | null;
  selectedBanner: CmsBanner | null;
  selectedNotice: CmsNotice | null;
  selectedAsset: CmsAsset | null;
  facets: CmsFacets;
};

export type CmsModuleKey = "overview" | "pages" | "sections" | "banners" | "notices" | "assets";

export type CmsFoundationResult = {
  state: CmsContentState;
  brand: typeof CMS_BRAND;
  counts: {
    pages: number;
    sections: number;
    banners: number;
    notices: number;
    assets: number;
    revisions: number;
  };
  validationSummary: {
    valid: number;
    warning: number;
    blocked: number;
  };
};
