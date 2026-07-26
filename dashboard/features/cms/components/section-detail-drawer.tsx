"use client";

import { useMemo, useState } from "react";
import { CmsLocalPreviewForm } from "@/features/cms/components/cms-local-preview-form";
import { CmsPreviewModeSelector } from "@/features/cms/components/cms-preview-mode-selector";
import { CmsPreviewShell } from "@/features/cms/components/cms-preview-shell";
import { CmsRevisionTimeline } from "@/features/cms/components/cms-revision-timeline";
import { CmsSectionPreview } from "@/features/cms/components/cms-section-preview";
import { CmsValidationSummary } from "@/features/cms/components/cms-validation-summary";
import { getSectionDefinition } from "@/features/cms/registry/section-registry";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { mockCmsAssets, mockCmsPages, mockCmsRevisions } from "@/mocks/cms-fixtures";
import { resolveValidation } from "@/lib/cms/query-filters";
import type { CmsPreviewMode, CmsSectionInstance } from "@/types/cms";

export function SectionDetailDrawerContent({
  section,
  previewMode,
  onPreviewModeChange,
}: {
  section: CmsSectionInstance;
  previewMode: CmsPreviewMode;
  onPreviewModeChange: (mode: CmsPreviewMode) => void;
}) {
  const [previewSection, setPreviewSection] = useState<CmsSectionInstance | null>(null);
  const activeSection = previewSection ?? section;
  const def = getSectionDefinition(section.sectionType);
  const page = mockCmsPages.find((p) => p.id === section.pageId);
  const validation = useMemo(() => resolveValidation(activeSection, "section"), [activeSection]);
  const assets = section.assetIds.map((id) => mockCmsAssets.find((a) => a.id === id)).filter(Boolean);

  return (
    <div className="space-y-4" data-testid="cms-section-drawer">
      <section>
        <h3 className="text-sm font-semibold text-gray-900">Section identity</h3>
        <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
          <div><dt className="text-jp-muted">Section ID</dt><dd>{section.id}</dd></div>
          <div><dt className="text-jp-muted">Label</dt><dd>{def?.label}</dd></div>
          <div className="sm:col-span-2"><dt className="text-jp-muted">Component key</dt><dd><code className="break-all text-xs">{def?.frontendComponentKey}</code></dd></div>
          <div><dt className="text-jp-muted">Assigned page</dt><dd>{page?.title}</dd></div>
          <div><dt className="text-jp-muted">Order</dt><dd>{section.sortOrder}</dd></div>
        </dl>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Theme & layout</h3>
        <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
          <div><dt className="text-jp-muted">Variant</dt><dd>{section.variant}</dd></div>
          <div><dt className="text-jp-muted">Theme mode</dt><dd>{section.themeMode}</dd></div>
          <div><dt className="text-jp-muted">Theme treatment</dt><dd>{section.themeTreatment}</dd></div>
          <div><dt className="text-jp-muted">Content width</dt><dd>{section.contentWidth}</dd></div>
          <div><dt className="text-jp-muted">Spacing</dt><dd>{section.spacing}</dd></div>
          <div><dt className="text-jp-muted">Alignment</dt><dd>{section.textAlignment}</dd></div>
          <div><dt className="text-jp-muted">Device visibility</dt><dd>{section.deviceVisibility}</dd></div>
        </dl>
      </section>

      {section.sectionType === "homepage.flightSearchContext" ? (
        <section className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm" data-testid="flight-search-boundary">
          <h3 className="font-semibold text-amber-900">Functional boundary</h3>
          <p className="mt-1 text-amber-900">
            Flight search mechanics (autocomplete, trip type, passengers, supplier calls, fare retrieval) are not CMS-controlled.
            CMS may only adjust surrounding labels and placement metadata.
          </p>
        </section>
      ) : null}

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Field values</h3>
        <dl className="mt-2 space-y-2 text-sm">
          {Object.entries(section.fields).map(([key, value]) => (
            <div key={key}>
              <dt className="text-jp-muted">{key}</dt>
              <dd className="break-words">{typeof value === "object" && value !== null ? JSON.stringify(value) : String(value ?? "—")}</dd>
            </div>
          ))}
        </dl>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Referenced assets</h3>
        {assets.length === 0 ? (
          <p className="mt-1 text-sm text-jp-muted">No assets referenced.</p>
        ) : (
          <ul className="mt-2 space-y-1 text-sm">
            {assets.map((asset) => asset && (
              <li key={asset.id}>
                {asset.id} — {asset.internalName} (<CmsStatusBadge status={asset.approvalStatus} />)
              </li>
            ))}
          </ul>
        )}
      </section>

      <CmsValidationSummary issues={validation.issues} />
      <CmsRevisionTimeline revisions={mockCmsRevisions} entityId={section.id} />

      <CmsLocalPreviewForm section={section} onApply={setPreviewSection} onReset={() => setPreviewSection(null)} dirty={Boolean(previewSection)} />

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Section preview</h3>
        <CmsPreviewModeSelector mode={previewMode} onChange={onPreviewModeChange} />
        <div className="mt-3">
          <CmsPreviewShell mode={previewMode} label={def?.label ?? section.sectionType} warnings={validation.issues.map((i) => i.message)}>
            <CmsSectionPreview section={activeSection} mode={previewMode} />
          </CmsPreviewShell>
        </div>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Future Next.js mapping</h3>
        <p className="mt-1 text-sm text-jp-muted">{def?.apiMappingNotes}</p>
        <p className="mt-1 font-mono text-xs break-all">→ {def?.frontendComponentKey}</p>
      </section>
    </div>
  );
}
