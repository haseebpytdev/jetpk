"use client";

import { useState } from "react";
import { getSectionDefinition, getSupportedVariants } from "@/features/cms/registry/section-registry";
import { isUnsafeUrl } from "@/features/cms/validation/link-validation";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import type { CmsLink, CmsSectionInstance } from "@/types/cms";

type Props = {
  section: CmsSectionInstance;
  onApply: (section: CmsSectionInstance) => void;
  onReset: () => void;
  dirty: boolean;
};

export function CmsLocalPreviewForm({ section, onApply, onReset, dirty }: Props) {
  const def = getSectionDefinition(section.sectionType);
  const variants = getSupportedVariants(section.sectionType);
  const [heading, setHeading] = useState(String(section.fields.heading ?? section.fields.title ?? ""));
  const [supportingText, setSupportingText] = useState(String(section.fields.supportingText ?? ""));
  const [eyebrow, setEyebrow] = useState(String(section.fields.eyebrow ?? ""));
  const [ctaLabel, setCtaLabel] = useState(
    typeof section.fields.primaryCta === "object" && section.fields.primaryCta !== null
      ? (section.fields.primaryCta as CmsLink).label
      : "",
  );
  const [ctaValue, setCtaValue] = useState(
    typeof section.fields.primaryCta === "object" && section.fields.primaryCta !== null
      ? (section.fields.primaryCta as CmsLink).value
      : "",
  );
  const [variant, setVariant] = useState(section.variant);
  const [themeTreatment, setThemeTreatment] = useState(section.themeTreatment);
  const [contentWidth, setContentWidth] = useState(section.contentWidth);
  const [spacing, setSpacing] = useState(section.spacing);
  const [alignment, setAlignment] = useState(section.textAlignment);
  const [linkError, setLinkError] = useState<string | null>(null);

  if (!def?.previewCapable) return null;

  const applyPreview = () => {
    if (ctaValue && isUnsafeUrl(ctaValue)) {
      setLinkError("Unsafe URL protocol is not allowed.");
      return;
    }
    setLinkError(null);
    const primaryCta: CmsLink | null = ctaLabel
      ? { type: "internal_route", label: ctaLabel, value: ctaValue || "/" }
      : null;
    onApply({
      ...section,
      variant,
      themeTreatment,
      contentWidth,
      spacing,
      textAlignment: alignment,
      fields: {
        ...section.fields,
        heading,
        supportingText,
        eyebrow,
        primaryCta,
      },
    });
  };

  const handleReset = () => {
    setHeading(String(section.fields.heading ?? section.fields.title ?? ""));
    setSupportingText(String(section.fields.supportingText ?? ""));
    setEyebrow(String(section.fields.eyebrow ?? ""));
    setCtaLabel(
      typeof section.fields.primaryCta === "object" && section.fields.primaryCta !== null
        ? (section.fields.primaryCta as CmsLink).label
        : "",
    );
    setCtaValue(
      typeof section.fields.primaryCta === "object" && section.fields.primaryCta !== null
        ? (section.fields.primaryCta as CmsLink).value
        : "",
    );
    setVariant(section.variant);
    setThemeTreatment(section.themeTreatment);
    setContentWidth(section.contentWidth);
    setSpacing(section.spacing);
    setAlignment(section.textAlignment);
    setLinkError(null);
    onReset();
  };

  return (
    <section className="rounded-xl border border-jp-border p-4" data-testid="cms-local-preview-form">
      <h3 className="text-sm font-semibold text-gray-900">Local preview editing</h3>
      {dirty ? (
        <p role="status" className="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
          Unsaved preview — changes are local to this session only.
        </p>
      ) : null}

      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        <div>
          <Label htmlFor="cms-preview-heading">Heading</Label>
          <input
            id="cms-preview-heading"
            className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm"
            value={heading}
            onChange={(e) => setHeading(e.target.value)}
            maxLength={120}
          />
        </div>
        <div>
          <Label htmlFor="cms-preview-eyebrow">Eyebrow</Label>
          <input
            id="cms-preview-eyebrow"
            className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm"
            value={eyebrow}
            onChange={(e) => setEyebrow(e.target.value)}
          />
        </div>
        <div className="sm:col-span-2">
          <Label htmlFor="cms-preview-supporting">Supporting copy</Label>
          <textarea
            id="cms-preview-supporting"
            className="mt-1 w-full rounded-xl border border-jp-border px-3 py-2 text-sm"
            rows={2}
            value={supportingText}
            onChange={(e) => setSupportingText(e.target.value)}
          />
        </div>
        <div>
          <Label htmlFor="cms-preview-cta-label">CTA label</Label>
          <input
            id="cms-preview-cta-label"
            className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm"
            value={ctaLabel}
            onChange={(e) => setCtaLabel(e.target.value)}
          />
        </div>
        <div>
          <Label htmlFor="cms-preview-cta-value">CTA destination</Label>
          <input
            id="cms-preview-cta-value"
            className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm"
            value={ctaValue}
            onChange={(e) => setCtaValue(e.target.value)}
            aria-invalid={Boolean(linkError)}
          />
          {linkError ? <p className="mt-1 text-xs text-red-700" role="alert">{linkError}</p> : null}
        </div>
        <div>
          <Label htmlFor="cms-preview-variant">Variant</Label>
          <Select id="cms-preview-variant" value={variant} onChange={(e) => setVariant(e.target.value as typeof variant)}>
            {variants.map((v) => (
              <option key={v} value={v}>{v}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="cms-preview-width">Content width</Label>
          <Select id="cms-preview-width" value={contentWidth} onChange={(e) => setContentWidth(e.target.value as typeof contentWidth)}>
            <option value="narrow">Narrow</option>
            <option value="standard">Standard</option>
            <option value="wide">Wide</option>
            <option value="fullBleed">Full bleed</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="cms-preview-spacing">Spacing</Label>
          <Select id="cms-preview-spacing" value={spacing} onChange={(e) => setSpacing(e.target.value as typeof spacing)}>
            <option value="compact">Compact</option>
            <option value="standard">Standard</option>
            <option value="spacious">Spacious</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="cms-preview-alignment">Alignment</Label>
          <Select id="cms-preview-alignment" value={alignment} onChange={(e) => setAlignment(e.target.value as typeof alignment)}>
            <option value="start">Start</option>
            <option value="center">Center</option>
            <option value="end">End</option>
          </Select>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        <Button type="button" size="sm" onClick={applyPreview}>
          Apply to preview
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={handleReset} disabled={!dirty}>
          Reset preview
        </Button>
      </div>
    </section>
  );
}
