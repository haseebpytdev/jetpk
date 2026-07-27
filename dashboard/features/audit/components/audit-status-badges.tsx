import type { AuditOutcome, AuditSeverity } from "@/types/access-control";

const severityStyles: Record<AuditSeverity, string> = {
  info: "bg-blue-50 text-blue-800 ring-blue-600/20",
  notice: "bg-cyan-50 text-cyan-900 ring-cyan-600/20",
  warning: "bg-amber-50 text-amber-900 ring-amber-600/20",
  critical: "bg-red-50 text-red-800 ring-red-600/20",
};

const outcomeStyles: Record<AuditOutcome, string> = {
  success: "bg-emerald-50 text-emerald-800 ring-emerald-600/20",
  failure: "bg-red-50 text-red-800 ring-red-600/20",
  partial: "bg-amber-50 text-amber-900 ring-amber-600/20",
  preview: "bg-violet-50 text-violet-800 ring-violet-600/20",
};

function Pill({ label, tone }: { label: string; tone: string }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${tone}`}>
      {label}
    </span>
  );
}

export function AuditSeverityBadge({ severity }: { severity: AuditSeverity }) {
  return <Pill label={severity} tone={severityStyles[severity]} />;
}

export function AuditOutcomeBadge({ outcome }: { outcome: AuditOutcome }) {
  return <Pill label={outcome} tone={outcomeStyles[outcome]} />;
}
