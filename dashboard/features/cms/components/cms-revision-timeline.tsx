import { Card, CardTitle } from "@/components/ui/card";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import type { CmsRevision } from "@/types/cms";

export function CmsRevisionTimeline({ revisions, entityId }: { revisions: CmsRevision[]; entityId?: string }) {
  const filtered = entityId ? revisions.filter((r) => r.entityId === entityId) : revisions;

  return (
    <Card className="p-4" data-testid="cms-revision-timeline">
      <CardTitle className="text-base">Revision history</CardTitle>
      {filtered.length === 0 ? (
        <p className="mt-2 text-sm text-jp-muted">No revision records for this item.</p>
      ) : (
        <ol className="mt-3 space-y-3">
          {filtered.map((rev) => (
            <li key={rev.id} className="rounded-lg border border-jp-border px-3 py-2 text-sm">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">v{rev.version}</span>
                <CmsStatusBadge status={rev.status} />
                <CmsStatusBadge status={rev.validation.valid ? "valid" : "blocked"} label={rev.validation.valid ? "Valid" : "Issues"} />
              </div>
              <p className="mt-1 text-gray-800">{rev.changeSummary}</p>
              <p className="mt-1 text-xs text-jp-muted">
                {rev.authorId} · {rev.timestamp.slice(0, 10)} · {rev.entityType} {rev.entityId}
              </p>
            </li>
          ))}
        </ol>
      )}
    </Card>
  );
}
