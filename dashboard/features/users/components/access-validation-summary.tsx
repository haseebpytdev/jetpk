import type { AccessValidationIssue } from "@/types/access-control";

const severityStyles: Record<AccessValidationIssue["severity"], string> = {
  error: "border-red-200 bg-red-50 text-red-900",
  warning: "border-amber-200 bg-amber-50 text-amber-900",
  info: "border-blue-200 bg-blue-50 text-blue-900",
};

export function AccessValidationSummary({ issues }: { issues: AccessValidationIssue[] }) {
  if (issues.length === 0) {
    return (
      <p className="text-sm text-jp-muted" data-testid="access-validation-summary">
        No validation issues.
      </p>
    );
  }

  return (
    <ul className="space-y-2" data-testid="access-validation-summary" aria-label="Validation issues">
      {issues.map((issue) => (
        <li
          key={`${issue.code}-${issue.fieldPath}`}
          className={`rounded-lg border px-3 py-2 text-sm ${severityStyles[issue.severity]}`}
        >
          <p className="font-medium">{issue.message}</p>
          <p className="mt-1 text-xs opacity-80">{issue.suggestedResolution}</p>
          {issue.blocking ? (
            <span className="mt-1 inline-block text-xs font-medium">Blocking</span>
          ) : null}
        </li>
      ))}
    </ul>
  );
}
