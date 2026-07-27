"use client";

import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import type { PermissionsSummaryMetrics } from "@/types/permissions";

const METRICS: { key: keyof PermissionsSummaryMetrics; label: string }[] = [
  { key: "totalPermissions", label: "Total permissions" },
  { key: "viewPermissions", label: "View permissions" },
  { key: "requestPermissions", label: "Request permissions" },
  { key: "approvalPermissions", label: "Approval permissions" },
  { key: "managePermissions", label: "Manage permissions" },
  { key: "exportPermissions", label: "Export permissions" },
  { key: "highRiskPermissions", label: "High-risk permissions" },
  { key: "permissionsRequiringPrerequisiteReview", label: "Prerequisite review" },
];

export function PermissionsSummaryMetrics({ summary }: { summary: PermissionsSummaryMetrics }) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8" data-testid="permissions-metric-grid">
      {METRICS.map((metric) => (
        <Card key={metric.key} className="p-3">
          <CardDescription className="text-xs">{metric.label}</CardDescription>
          <CardTitle className="mt-1 text-xl tabular-nums">{summary[metric.key]}</CardTitle>
        </Card>
      ))}
    </div>
  );
}
