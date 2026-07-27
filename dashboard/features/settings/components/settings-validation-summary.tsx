import type { AccessValidationIssue } from "@/types/access-control";

const severityStyles: Record<AccessValidationIssue["severity"], string> = {
  error: "border-red-200 bg-red-50 text-red-900",
  warning: "border-amber-200 bg-amber-50 text-amber-900",
  info: "border-blue-200 bg-blue-50 text-blue-900",
};

type Props = {
  issues: AccessValidationIssue[];
  title?: string;
  filter?: "all" | "valid" | "warning" | "blocked";
};

function filterIssues(issues: AccessValidationIssue[], filter: Props["filter"]): AccessValidationIssue[] {
  if (filter === "valid") return issues.filter((i) => i.severity === "info");
  if (filter === "warning") return issues.filter((i) => i.severity === "warning");
  if (filter === "blocked") return issues.filter((i) => i.blocking);
  return issues;
}

export function SettingsValidationSummary({ issues, title = "Validation summary", filter = "all" }: Props) {
  const visible = filterIssues(issues, filter);

  if (visible.length === 0) {
    return (
      <section className="rounded-xl border border-jp-border bg-white p-4" data-testid="settings-validation-summary">
        <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
        <p className="mt-2 text-sm text-jp-muted">No validation issues for the current preview values.</p>
      </section>
    );
  }

  const blockingCount = visible.filter((i) => i.blocking).length;
  const warningCount = visible.filter((i) => i.severity === "warning").length;

  return (
    <section className="rounded-xl border border-jp-border bg-white p-4" data-testid="settings-validation-summary">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
        <p className="text-xs text-jp-muted">
          {visible.length} issue{visible.length === 1 ? "" : "s"}
          {blockingCount > 0 ? ` · ${blockingCount} blocking` : ""}
          {warningCount > 0 ? ` · ${warningCount} warning${warningCount === 1 ? "" : "s"}` : ""}
        </p>
      </div>
      <ul className="mt-3 space-y-2" aria-label="Settings validation issues">
        {visible.map((issue) => (
          <li
            key={`${issue.code}-${issue.fieldPath}`}
            className={`rounded-lg border px-3 py-2 text-sm ${severityStyles[issue.severity]}`}
          >
            <p className="font-medium">{issue.message}</p>
            <p className="mt-1 text-xs opacity-80">{issue.suggestedResolution}</p>
            <p className="mt-1 font-mono text-xs opacity-70">{issue.fieldPath}</p>
            {issue.blocking ? <span className="mt-1 inline-block text-xs font-medium">Blocking</span> : null}
          </li>
        ))}
      </ul>
    </section>
  );
}
