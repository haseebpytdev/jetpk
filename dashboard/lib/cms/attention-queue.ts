import { getSectionDefinition } from "@/features/cms/registry/section-registry";
import { validateAsset, validateBanner, validateSectionInstance } from "@/features/cms/validation/cms-validation";
import { mergeValidationIssues, validateCmsLink } from "@/features/cms/validation/link-validation";
import {
  mockCmsAssets,
  mockCmsBanners,
  mockCmsNotices,
  mockCmsPages,
  mockCmsSections,
} from "@/mocks/cms-fixtures";
import type { CmsAttentionItem } from "@/types/cms";

function sectionValidation(sectionId: string) {
  const section = mockCmsSections.find((s) => s.id === sectionId);
  if (!section) return mergeValidationIssues([]);
  const page = mockCmsPages.find((p) => p.id === section.pageId);
  const siblings = mockCmsSections.filter((s) => s.pageId === section.pageId);
  return mergeValidationIssues(validateSectionInstance(section, page?.pageType ?? "", siblings));
}

export function buildCmsAttentionQueue(): CmsAttentionItem[] {
  const items: CmsAttentionItem[] = [];

  for (const asset of mockCmsAssets) {
    const validation = mergeValidationIssues(validateAsset(asset));
    if (!asset.mobile.width) {
      items.push({
        id: `att-asset-mobile-${asset.id}`,
        category: "missing_mobile_asset",
        categoryLabel: "Missing mobile asset",
        title: asset.internalName,
        description: "Mobile variant metadata is incomplete.",
        href: `/cms/assets?selected=${encodeURIComponent(asset.id)}`,
        linkLabel: "View asset",
        recordId: asset.id,
      });
    }
    if (!asset.nightVariant && asset.dayVariant) {
      items.push({
        id: `att-asset-night-${asset.id}`,
        category: "missing_night_asset",
        categoryLabel: "Missing night asset",
        title: asset.internalName,
        description: "Day variant exists without a paired night asset.",
        href: `/cms/assets?selected=${encodeURIComponent(asset.id)}`,
        linkLabel: "View asset",
        recordId: asset.id,
      });
    }
    if (!asset.altText.trim()) {
      items.push({
        id: `att-asset-alt-${asset.id}`,
        category: "missing_alt_text",
        categoryLabel: "Missing alt text",
        title: asset.id,
        description: "Asset requires descriptive alt text.",
        href: `/cms/assets?selected=${encodeURIComponent(asset.id)}`,
        linkLabel: "View asset",
        recordId: asset.id,
      });
    }
    if (asset.approvalStatus === "unapproved" || asset.approvalStatus === "rejected") {
      items.push({
        id: `att-asset-unapproved-${asset.id}`,
        category: "unapproved_asset",
        categoryLabel: "Unapproved asset",
        title: asset.internalName,
        description: "Asset is not approved for publication.",
        href: `/cms/assets?selected=${encodeURIComponent(asset.id)}`,
        linkLabel: "View asset",
        recordId: asset.id,
      });
    }
    for (const issue of validation.issues.filter((i) => i.blocking)) {
      if (items.some((x) => x.recordId === asset.id && x.category === issue.code)) continue;
      items.push({
        id: `att-asset-${issue.code}-${asset.id}`,
        category: issue.code,
        categoryLabel: issue.code.replace(/_/g, " "),
        title: asset.internalName,
        description: issue.message,
        href: `/cms/assets?selected=${encodeURIComponent(asset.id)}`,
        linkLabel: "View asset",
        recordId: asset.id,
      });
    }
  }

  for (const section of mockCmsSections) {
    const validation = sectionValidation(section.id);
    const def = getSectionDefinition(section.sectionType);
    const page = mockCmsPages.find((p) => p.id === section.pageId);
    if (def && page && !def.supportedPageTypes.includes(page.pageType)) {
      items.push({
        id: `att-section-placement-${section.id}`,
        category: "unsupported_section_placement",
        categoryLabel: "Unsupported placement",
        title: def.label,
        description: `${section.sectionType} is not supported on ${page.pageType}.`,
        href: `/cms/sections?selected=${encodeURIComponent(section.id)}`,
        linkLabel: "View section",
        recordId: section.id,
      });
    }
    for (const issue of validation.issues) {
      if (issue.code === "duplicate_singleton_section") {
        items.push({
          id: `att-dup-${section.id}`,
          category: "duplicate_singleton_section",
          categoryLabel: "Duplicate singleton",
          title: def?.label ?? section.sectionType,
          description: issue.message,
          href: `/cms/sections?selected=${encodeURIComponent(section.id)}`,
          linkLabel: "View section",
          recordId: section.id,
        });
      }
      if (issue.code === "publication_window_conflict") {
        items.push({
          id: `att-pub-${section.id}`,
          category: "publication_window_conflict",
          categoryLabel: "Publication window",
          title: section.id,
          description: issue.message,
          href: `/cms/sections?selected=${encodeURIComponent(section.id)}`,
          linkLabel: "View section",
          recordId: section.id,
        });
      }
    }
    const primaryCta = section.fields.primaryCta;
    if (primaryCta && typeof primaryCta === "object" && primaryCta !== null && "type" in primaryCta) {
      const linkIssues = validateCmsLink(primaryCta, section.id, "fields.primaryCta");
      for (const issue of linkIssues) {
        items.push({
          id: `att-cta-${section.id}-${issue.code}`,
          category: issue.code,
          categoryLabel: "Invalid CTA",
          title: def?.label ?? section.sectionType,
          description: issue.message,
          href: `/cms/sections?selected=${encodeURIComponent(section.id)}`,
          linkLabel: "View section",
          recordId: section.id,
        });
      }
    }
  }

  for (const banner of mockCmsBanners) {
    const validation = mergeValidationIssues(validateBanner(banner));
    if (banner.desktopAspectRatio !== banner.mobileAspectRatio && banner.family === "hero") {
      items.push({
        id: `att-ratio-${banner.id}`,
        category: "aspect_ratio_mismatch",
        categoryLabel: "Aspect ratio mismatch",
        title: banner.title,
        description: `Desktop ${banner.desktopAspectRatio} vs mobile ${banner.mobileAspectRatio}.`,
        href: `/cms/banners?selected=${encodeURIComponent(banner.id)}`,
        linkLabel: "View banner",
        recordId: banner.id,
      });
    }
    for (const issue of validation.issues) {
      items.push({
        id: `att-banner-${issue.code}-${banner.id}`,
        category: issue.code,
        categoryLabel: issue.code.replace(/_/g, " "),
        title: banner.title,
        description: issue.message,
        href: `/cms/banners?selected=${encodeURIComponent(banner.id)}`,
        linkLabel: "View banner",
        recordId: banner.id,
      });
    }
    if (banner.cta?.type === "external_url") {
      items.push({
        id: `att-ext-${banner.id}`,
        category: "external_url_review",
        categoryLabel: "External URL review",
        title: banner.title,
        description: "External CTA requires editorial review.",
        href: `/cms/banners?selected=${encodeURIComponent(banner.id)}`,
        linkLabel: "View banner",
        recordId: banner.id,
      });
    }
  }

  for (const notice of mockCmsNotices) {
    if (notice.status === "scheduled") {
      items.push({
        id: `att-sched-notice-${notice.id}`,
        category: "scheduled_review",
        categoryLabel: "Scheduled review",
        title: notice.title,
        description: "Scheduled notice requires review before publication.",
        href: `/cms/notices?selected=${encodeURIComponent(notice.id)}`,
        linkLabel: "View notice",
        recordId: notice.id,
      });
    }
    if (notice.cta) {
      const linkIssues = validateCmsLink(notice.cta, notice.id, "cta");
      for (const issue of linkIssues.filter((i) => i.blocking)) {
        items.push({
          id: `att-notice-cta-${notice.id}`,
          category: issue.code,
          categoryLabel: "Unsafe CTA",
          title: notice.title,
          description: issue.message,
          href: `/cms/notices?selected=${encodeURIComponent(notice.id)}`,
          linkLabel: "View notice",
          recordId: notice.id,
        });
      }
    }
  }

  const seen = new Set<string>();
  return items.filter((item) => {
    const key = `${item.category}-${item.recordId}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}
