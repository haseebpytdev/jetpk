import type { CmsLink, CmsValidationIssue, CmsValidationResult } from "@/types/cms";

const APPROVED_INTERNAL_ROUTES = [
  "/",
  "/about",
  "/contact",
  "/faq",
  "/privacy",
  "/terms",
  "/refund",
  "/support",
  "/flights/search",
  "/flights/results",
  "/umrah",
  "/travel-guidance",
] as const;

const UNSAFE_PROTOCOLS = /^(javascript|data|vbscript|file):/i;

export function validateCmsLink(link: CmsLink, recordId: string, fieldPath: string): CmsValidationIssue[] {
  const issues: CmsValidationIssue[] = [];

  if (link.type === "none") {
    return issues;
  }

  if (!link.label.trim()) {
    issues.push({
      severity: "error",
      code: "cta_without_label",
      message: "Link label is required.",
      fieldPath,
      recordId,
      suggestedResolution: "Add a descriptive link label.",
      blocking: true,
    });
  }

  if (link.type === "internal_route") {
    const path = link.value.trim();
    if (!APPROVED_INTERNAL_ROUTES.some((r) => path === r || path.startsWith(`${r}?`))) {
      issues.push({
        severity: "error",
        code: "invalid_internal_route",
        message: `Internal route "${path}" is not in the approved registry.`,
        fieldPath,
        recordId,
        suggestedResolution: "Use an approved internal route pattern.",
        blocking: true,
      });
    }
  }

  if (link.type === "external_url") {
    if (UNSAFE_PROTOCOLS.test(link.value.trim())) {
      issues.push({
        severity: "error",
        code: "unsafe_link_protocol",
        message: "External URL uses a disallowed protocol.",
        fieldPath,
        recordId,
        suggestedResolution: "Use https:// URLs only.",
        blocking: true,
      });
    } else {
      issues.push({
        severity: "warning",
        code: "external_url_review",
        message: "External URL requires editorial review.",
        fieldPath,
        recordId,
        suggestedResolution: "Confirm the destination is trusted before publishing.",
        blocking: false,
      });
    }
  }

  if (link.type === "whatsapp_action" && !link.value.startsWith("https://wa.me/")) {
    issues.push({
      severity: "warning",
      code: "whatsapp_format",
      message: "WhatsApp action should use approved wa.me format.",
      fieldPath,
      recordId,
      suggestedResolution: "Use https://wa.me/ with synthetic preview number.",
      blocking: false,
    });
  }

  return issues;
}

export function isApprovedInternalRoute(path: string): boolean {
  return APPROVED_INTERNAL_ROUTES.some((r) => path === r || path.startsWith(`${r}?`));
}

export function isUnsafeUrl(value: string): boolean {
  return UNSAFE_PROTOCOLS.test(value.trim());
}

export function mergeValidationIssues(issues: CmsValidationIssue[]): CmsValidationResult {
  return {
    valid: !issues.some((i) => i.blocking),
    issues,
  };
}
