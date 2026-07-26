import { Card } from "@/components/ui/card";
import type { CmsQuery } from "@/types/cms";

const LABELS: Partial<Record<keyof CmsQuery, string>> = {
  status: "Status",
  pageType: "Page type",
  sectionType: "Section type",
  themeMode: "Theme",
  locale: "Locale",
  assetStatus: "Asset approval",
  bannerFamily: "Banner family",
  noticeSeverity: "Severity",
  validationState: "Validation",
  audience: "Audience",
  placement: "Placement",
  search: "Search",
};

export function CmsActiveFilters({ query }: { query: CmsQuery }) {
  const active = (Object.keys(LABELS) as (keyof CmsQuery)[])
    .map((key) => {
      const value = query[key];
      if (value === "all" || value === "" || value === null || value === undefined) return null;
      if (typeof value === "boolean") return null;
      return { key, label: LABELS[key], value: String(value) };
    })
    .filter(Boolean) as { key: string; label: string; value: string }[];

  if (active.length === 0) return null;

  return (
    <Card className="flex flex-wrap gap-2 p-3" data-testid="cms-active-filters">
      <span className="text-sm font-medium text-gray-900">Active filters:</span>
      {active.map((f) => (
        <span key={f.key} className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-900 ring-1 ring-emerald-600/20">
          {f.label}: {f.value}
        </span>
      ))}
    </Card>
  );
}
