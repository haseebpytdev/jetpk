"use client";

import { useMemo, useState } from "react";
import { CmsPageComposition } from "@/features/cms/components/cms-page-composition";
import { CmsPageLocalEditor } from "@/features/cms/components/cms-page-local-editor";
import { CmsMediaFieldCards } from "@/features/cms/components/cms-media-field-cards";
import { CmsPreviewModeSelector } from "@/features/cms/components/cms-preview-mode-selector";
import { CmsPreviewShell } from "@/features/cms/components/cms-preview-shell";
import { CmsRevisionTimeline } from "@/features/cms/components/cms-revision-timeline";
import { CmsValidationSummary } from "@/features/cms/components/cms-validation-summary";
import { CmsSectionPreview } from "@/features/cms/components/cms-section-preview";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { mockCmsRevisions, mockCmsSections } from "@/mocks/cms-fixtures";
import { resolveValidation } from "@/lib/cms/query-filters";
import type { CmsPage, CmsPreviewMode } from "@/types/cms";

export function PageDetailDrawerContent({ page, previewMode, onPreviewModeChange }: { page: CmsPage; previewMode: CmsPreviewMode; onPreviewModeChange: (mode: CmsPreviewMode) => void }) {
  const validation = resolveValidation(page, "page");
  const sections = useMemo(
    () => page.sectionIds.map((id) => mockCmsSections.find((s) => s.id === id)).filter(Boolean),
    [page.sectionIds],
  );
  const [activeSectionId, setActiveSectionId] = useState<string | null>(sections[0]?.id ?? null);
  const activeSection = sections.find((section) => section?.id === activeSectionId) ?? sections[0];
  const mediaAssetIds = useMemo(
    () => Array.from(new Set(sections.flatMap((section) => section?.assetIds ?? []))),
    [sections],
  );

  return (
    <div className="space-y-4" data-testid="cms-page-drawer">
      <CmsPageLocalEditor page={page} />
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(280px,42%)]" data-testid="cms-page-editor">
        <div className="space-y-4">
          <section>
            <h3 className="text-sm font-semibold text-gray-900">Page identity</h3>
            <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
              <div><dt className="text-jp-muted">Page ID</dt><dd>{page.id}</dd></div>
              <div><dt className="text-jp-muted">Slug</dt><dd>{page.slug}</dd></div>
              <div><dt className="text-jp-muted">Page type</dt><dd>{page.pageType}</dd></div>
              <div><dt className="text-jp-muted">Locale</dt><dd>{page.locale}</dd></div>
              <div><dt className="text-jp-muted">Status</dt><dd><CmsStatusBadge status={page.status} /></dd></div>
              <div><dt className="text-jp-muted">Visibility</dt><dd>{page.visibility}</dd></div>
            </dl>
          </section>

          <section>
            <h3 className="text-sm font-semibold text-gray-900">SEO metadata</h3>
            <dl className="mt-2 space-y-2 text-sm">
              <div><dt className="text-jp-muted">SEO title</dt><dd>{page.seoTitle}</dd></div>
              <div><dt className="text-jp-muted">SEO description</dt><dd>{page.seoDescription}</dd></div>
            </dl>
          </section>

          <section>
            <h3 className="text-sm font-semibold text-gray-900">Publication</h3>
            <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
              <div><dt className="text-jp-muted">Revision</dt><dd>{page.revisionNumber}</dd></div>
              <div><dt className="text-jp-muted">Last updated</dt><dd>{page.lastUpdatedDate}</dd></div>
              <div><dt className="text-jp-muted">Start date</dt><dd>{page.publicationWindow.startDate ?? "—"}</dd></div>
              <div><dt className="text-jp-muted">End date</dt><dd>{page.publicationWindow.endDate ?? "—"}</dd></div>
              <div><dt className="text-jp-muted">Preview available</dt><dd>{page.previewAvailable ? "Yes" : "No"}</dd></div>
            </dl>
          </section>

          <CmsPageComposition
            page={page}
            activeSectionId={activeSectionId}
            onActiveSectionChange={setActiveSectionId}
          />
          <CmsMediaFieldCards assetIds={mediaAssetIds} />
          <CmsValidationSummary issues={validation.issues} />
        </div>

        <aside className="h-fit space-y-3 lg:sticky lg:top-4" data-testid="cms-page-preview-panel">
          <h3 className="text-sm font-semibold text-gray-900">Live preview</h3>
          <CmsPreviewModeSelector mode={previewMode} onChange={onPreviewModeChange} />
          <CmsPreviewShell mode={previewMode} label={page.title} large>
            <div className="space-y-4">
              {activeSection ? <CmsSectionPreview section={activeSection} mode={previewMode} compact /> : null}
              {sections
                .filter((section) => section && section.id !== activeSection?.id)
                .map((section) =>
                  section ? <CmsSectionPreview key={section.id} section={section} mode={previewMode} compact /> : null,
                )}
            </div>
          </CmsPreviewShell>
        </aside>
      </div>

      <CmsRevisionTimeline revisions={mockCmsRevisions} entityId={page.id} />

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Future Next.js mapping</h3>
        <p className="mt-1 text-sm text-jp-muted">
          Page sections map to trusted Next.js components via stable <code>frontendComponentKey</code> values from the section registry.
        </p>
      </section>
    </div>
  );
}
