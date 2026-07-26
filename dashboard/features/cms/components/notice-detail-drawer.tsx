"use client";

import { CmsRevisionTimeline } from "@/features/cms/components/cms-revision-timeline";
import { CmsValidationSummary } from "@/features/cms/components/cms-validation-summary";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { mockCmsRevisions } from "@/mocks/cms-fixtures";
import { validatePublicationWindow } from "@/features/cms/validation/cms-validation";
import { validateCmsLink } from "@/features/cms/validation/link-validation";
import type { CmsNotice } from "@/types/cms";

export function NoticeDetailDrawerContent({ notice }: { notice: CmsNotice }) {
  const windowIssues = validatePublicationWindow(notice.id, notice.startDate, notice.endDate);
  const ctaIssues = notice.cta ? validateCmsLink(notice.cta, notice.id, "cta") : [];
  const issues = [...windowIssues, ...ctaIssues];

  return (
    <div className="space-y-4" data-testid="cms-notice-drawer">
      <section>
        <h3 className="text-sm font-semibold text-gray-900">Notice identity</h3>
        <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
          <div><dt className="text-jp-muted">Notice ID</dt><dd>{notice.id}</dd></div>
          <div><dt className="text-jp-muted">Severity</dt><dd><CmsStatusBadge status={notice.severity} /></dd></div>
          <div><dt className="text-jp-muted">Placement</dt><dd>{notice.placement}</dd></div>
          <div><dt className="text-jp-muted">Audience</dt><dd>{notice.audience}</dd></div>
          <div><dt className="text-jp-muted">Dismissible</dt><dd>{notice.dismissible ? "Yes" : "No"}</dd></div>
          <div><dt className="text-jp-muted">Status</dt><dd><CmsStatusBadge status={notice.status} /></dd></div>
        </dl>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Publication window</h3>
        <p className="mt-1 text-sm">{notice.startDate} → {notice.endDate ?? "open"}</p>
      </section>

      <CmsValidationSummary issues={issues} />
      <CmsRevisionTimeline revisions={mockCmsRevisions} entityId={notice.id} />

      <section data-testid="cms-notice-preview">
        <h3 className="text-sm font-semibold text-gray-900">Notice preview</h3>
        <p className="mt-1 text-xs text-jp-muted">Dashboard preview only — representative placement treatment.</p>
        <NoticePlacementPreview notice={notice} />
      </section>
    </div>
  );
}

function NoticePlacementPreview({ notice }: { notice: CmsNotice }) {
  const severityTone =
    notice.severity === "urgent" || notice.severity === "warning"
      ? "border-amber-300 bg-amber-50 text-amber-900"
      : notice.severity === "maintenance"
        ? "border-blue-300 bg-blue-50 text-blue-900"
        : "border-emerald-300 bg-emerald-50 text-emerald-900";

  if (notice.placement === "global_strip") {
    return (
      <div className={`mt-3 rounded-lg border px-4 py-2 text-sm ${severityTone}`} role="status">
        <strong>{notice.title}</strong> — {notice.message}
      </div>
    );
  }

  return (
    <div className={`mt-3 rounded-xl border p-4 ${severityTone}`}>
      <p className="font-semibold">{notice.title}</p>
      <p className="mt-1 text-sm">{notice.message}</p>
      {notice.cta ? <span className="mt-2 inline-block text-sm font-medium underline">{notice.cta.label}</span> : null}
    </div>
  );
}
