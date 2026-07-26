import type { CmsAsset, CmsBanner, CmsSectionInstance, CmsValidationIssue } from "@/types/cms";
import { CMS_BANNER_FAMILY_RULES } from "@/features/cms/registry/section-registry";
import { getSectionDefinition } from "@/features/cms/registry/section-registry";
import { mergeValidationIssues } from "@/features/cms/validation/link-validation";

export function validateAsset(asset: CmsAsset): CmsValidationIssue[] {
  const issues: CmsValidationIssue[] = [];

  if (!asset.altText.trim()) {
    issues.push({
      severity: "error",
      code: "missing_alt_text",
      message: "Asset alt text is required.",
      fieldPath: "altText",
      recordId: asset.id,
      suggestedResolution: "Add descriptive alt text for accessibility.",
      blocking: true,
    });
  } else if (asset.altText.trim().length < 8) {
    issues.push({
      severity: "warning",
      code: "weak_alt_text",
      message: "Alt text may be too short for screen readers.",
      fieldPath: "altText",
      recordId: asset.id,
      suggestedResolution: "Expand alt text with meaningful context.",
      blocking: false,
    });
  }

  if (asset.approvalStatus === "unapproved" || asset.approvalStatus === "rejected") {
    issues.push({
      severity: "error",
      code: "unapproved_asset",
      message: "Asset is not approved for publication.",
      fieldPath: "approvalStatus",
      recordId: asset.id,
      suggestedResolution: "Submit asset for approval or replace with approved asset.",
      blocking: true,
    });
  }

  return issues;
}

export function validatePublicationWindow(
  recordId: string,
  startDate: string | null,
  endDate: string | null,
): CmsValidationIssue[] {
  const issues: CmsValidationIssue[] = [];
  if (startDate && endDate && endDate < startDate) {
    issues.push({
      severity: "error",
      code: "publication_window_conflict",
      message: "End date is before start date.",
      fieldPath: "publicationWindow",
      recordId,
      suggestedResolution: "Set end date after start date.",
      blocking: true,
    });
  }
  return issues;
}

export function validateSectionInstance(
  section: CmsSectionInstance,
  pageType: string,
  siblingSections: CmsSectionInstance[],
): CmsValidationIssue[] {
  const issues: CmsValidationIssue[] = [];
  const def = getSectionDefinition(section.sectionType);

  if (!def) {
    issues.push({
      severity: "error",
      code: "unsupported_section",
      message: `Unknown section type ${section.sectionType}.`,
      fieldPath: "sectionType",
      recordId: section.id,
      suggestedResolution: "Use a registered section type.",
      blocking: true,
    });
    return issues;
  }

  if (!def.supportedPageTypes.includes(pageType as (typeof def.supportedPageTypes)[number])) {
    issues.push({
      severity: "error",
      code: "unsupported_page_placement",
      message: `Section ${section.sectionType} is not allowed on ${pageType}.`,
      fieldPath: "sectionType",
      recordId: section.id,
      suggestedResolution: "Move section to a supported page type.",
      blocking: true,
    });
  }

  if (!def.supportedThemeModes.includes(section.themeMode)) {
    issues.push({
      severity: "error",
      code: "unsupported_theme_mode",
      message: `Theme mode ${section.themeMode} is not supported for ${section.sectionType}.`,
      fieldPath: "themeMode",
      recordId: section.id,
      suggestedResolution: "Choose a supported theme mode.",
      blocking: true,
    });
  }

  if (!def.supportedVariants.includes(section.variant)) {
    issues.push({
      severity: "warning",
      code: "unsupported_variant",
      message: `Variant ${section.variant} is not listed for ${section.sectionType}.`,
      fieldPath: "variant",
      recordId: section.id,
      suggestedResolution: "Select a supported variant.",
      blocking: false,
    });
  }

  if (def.maxPerPage !== null) {
    const count = siblingSections.filter((s) => s.sectionType === section.sectionType).length;
    if (count > def.maxPerPage) {
      issues.push({
        severity: "error",
        code: "duplicate_singleton_section",
        message: `Only ${def.maxPerPage} instance(s) of ${section.sectionType} allowed per page.`,
        fieldPath: "sectionType",
        recordId: section.id,
        suggestedResolution: "Remove duplicate section instances.",
        blocking: true,
      });
    }
  }

  for (const field of def.requiredFields) {
    const value = section.fields[field];
    if (value === null || value === undefined || value === "") {
      issues.push({
        severity: "error",
        code: "missing_required_field",
        message: `Required field "${field}" is missing.`,
        fieldPath: `fields.${field}`,
        recordId: section.id,
        suggestedResolution: `Provide a value for ${field}.`,
        blocking: true,
      });
    }
  }

  issues.push(
    ...validatePublicationWindow(
      section.id,
      section.publicationWindow.startDate,
      section.publicationWindow.endDate,
    ),
  );

  return issues;
}

export function validateBanner(banner: CmsBanner): CmsValidationIssue[] {
  const issues: CmsValidationIssue[] = [];
  const rules = CMS_BANNER_FAMILY_RULES[banner.family];

  if (!rules) {
    return issues;
  }

  if (rules.requiresAltText && !banner.altText.trim()) {
    issues.push({
      severity: "error",
      code: "missing_alt_text",
      message: "Banner alt text is required.",
      fieldPath: "altText",
      recordId: banner.id,
      suggestedResolution: "Add descriptive alt text.",
      blocking: true,
    });
  }

  if (banner.placements.some((p) => !rules.placements.includes(p))) {
    issues.push({
      severity: "error",
      code: "invalid_banner_family_placement",
      message: `Placement not allowed for ${banner.family} banner family.`,
      fieldPath: "placements",
      recordId: banner.id,
      suggestedResolution: `Use placements: ${rules.placements.join(", ")}.`,
      blocking: true,
    });
  }

  issues.push(
    ...validatePublicationWindow(
      banner.id,
      banner.publicationWindow.startDate,
      banner.publicationWindow.endDate,
    ),
  );

  return mergeValidationIssues(issues).issues;
}

export function validateOfferCardCount(offerCount: number, recordId: string): CmsValidationIssue[] {
  if (offerCount <= 3) {
    return [];
  }
  return [
    {
      severity: "warning",
      code: "carousel_required",
      message: "More than three offer cards requires carousel presentation.",
      fieldPath: "displayVariant",
      recordId,
      suggestedResolution: "Set display variant to carousel.",
      blocking: false,
    },
  ];
}
