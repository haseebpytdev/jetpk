"use client";

import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import type { UsersSummaryMetrics } from "@/types/users";

const METRICS: { key: keyof UsersSummaryMetrics; label: string }[] = [
  { key: "totalUsers", label: "Total users" },
  { key: "activeUsers", label: "Active users" },
  { key: "invitedUsers", label: "Invited users" },
  { key: "lockedUsers", label: "Locked users" },
  { key: "suspendedUsers", label: "Suspended users" },
  { key: "mfaEnabledUsers", label: "MFA enabled" },
  { key: "usersWithoutRoles", label: "Without roles" },
  { key: "usersRequiringReview", label: "Requiring review" },
];

export function UsersSummaryMetrics({ summary }: { summary: UsersSummaryMetrics }) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8" data-testid="users-metric-grid">
      {METRICS.map((metric) => (
        <Card key={metric.key} className="p-3">
          <CardDescription className="text-xs">{metric.label}</CardDescription>
          <CardTitle className="mt-1 text-xl tabular-nums">{summary[metric.key]}</CardTitle>
        </Card>
      ))}
    </div>
  );
}
