"use client";

import { useMemo } from "react";
import { CmsPageComposition } from "@/features/cms/components/cms-page-composition";
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

  return (
    <div className="space-y-4" data-testid="cms-page-drawer">
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
          <div><dt className="text-jp-muted">Social title</dt><dd>{page.socialTitle}</dd></div>
          <div><dt className="text-jp-muted">Social description</dt><dd>{page.socialDescription}</dd></div>
        </dl>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Publication</h3>
        <p className="mt-1 text-sm">
          Window: {page.publicationWindow.startDate ?? "—"} → {page.publicationWindow.endDate ?? "open"}
        </p>
        <p className="text-sm">Revision {page.revisionNumber} · Updated {page.lastUpdatedDate.slice(0, 10)} by {page.updatedByUserId}</p>
      </section>

      <CmsPageComposition page={page} />
      <CmsValidationSummary issues={validation.issues} />
      <CmsRevisionTimeline revisions={mockCmsRevisions} entityId={page.id} />

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Page preview</h3>
        <div className="mt-2">
          <CmsPreviewModeSelector mode={previewMode} onChange={onPreviewModeChange} />
        </div>
        <div className="mt-3">
          <CmsPreviewShell mode={previewMode} label={page.title}>
            <div className="space-y-4">
              {sections.map((section) =>
                section ? <CmsSectionPreview key={section.id} section={section} mode={previewMode} compact /> : null,
              )}
            </div>
          </CmsPreviewShell>
        </div>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Future Next.js mapping</h3>
        <p className="mt-1 text-sm text-jp-muted">
          Page sections map to trusted Next.js components via stable <code>frontendComponentKey</code> values from the section registry.
        </p>
      </section>
    </div>
  );
}
