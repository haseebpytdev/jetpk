"use client";

import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import type { RolesSummaryMetrics } from "@/types/roles";

const METRICS: { key: keyof RolesSummaryMetrics; label: string }[] = [
  { key: "totalRoles", label: "Total roles" },
  { key: "activeRoles", label: "Active roles" },
  { key: "protectedSystemRoles", label: "Protected system roles" },
  { key: "customRoles", label: "Custom roles" },
  { key: "rolesWithHighRiskPermissions", label: "High-risk access" },
  { key: "rolesRequiringReview", label: "Requiring review" },
  { key: "unusedRoles", label: "Unused roles" },
  { key: "incompleteRoles", label: "Incomplete roles" },
];

export function RolesSummaryMetrics({ summary }: { summary: RolesSummaryMetrics }) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8" data-testid="roles-metric-grid">
      {METRICS.map((metric) => (
        <Card key={metric.key} className="p-3">
          <CardDescription className="text-xs">{metric.label}</CardDescription>
          <CardTitle className="mt-1 text-xl tabular-nums">{summary[metric.key]}</CardTitle>
        </Card>
      ))}
    </div>
  );
}
