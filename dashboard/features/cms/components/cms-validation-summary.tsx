import { CmsStatusBadge } from "@/components/ui/status-badge";
import { Card, CardTitle } from "@/components/ui/card";
import type { CmsValidationIssue } from "@/types/cms";

export function CmsValidationSummary({ issues, title = "Validation summary" }: { issues: CmsValidationIssue[]; title?: string }) {
  if (issues.length === 0) {
    return (
      <Card className="p-4" data-testid="cms-validation-summary">
        <CardTitle className="text-base">{title}</CardTitle>
        <p className="mt-2 text-sm text-jp-muted">
          <CmsStatusBadge status="valid" label="Valid — no issues" />
        </p>
      </Card>
    );
  }

  return (
    <Card className="p-4" data-testid="cms-validation-summary">
      <CardTitle className="text-base">{title}</CardTitle>
      <ul className="mt-3 space-y-2" role="list">
        {issues.map((issue, index) => (
          <li
            key={`${issue.code}-${issue.fieldPath}-${index}`}
            className="rounded-lg border border-jp-border px-3 py-2 text-sm"
            data-severity={issue.severity}
          >
            <div className="flex flex-wrap items-center gap-2">
              <CmsStatusBadge
                status={issue.blocking ? "blocked" : issue.severity === "warning" ? "warning" : "valid"}
                label={issue.blocking ? "Blocking" : issue.severity}
              />
              <span className="font-medium text-gray-900">{issue.code.replace(/_/g, " ")}</span>
            </div>
            <p className="mt-1 text-gray-800">{issue.message}</p>
            <p className="mt-1 text-xs text-jp-muted">
              Field: {issue.fieldPath} · {issue.suggestedResolution}
            </p>
          </li>
        ))}
      </ul>
    </Card>
  );
}
