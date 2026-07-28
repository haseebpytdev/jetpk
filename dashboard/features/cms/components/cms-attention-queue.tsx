import { DashboardLink } from "@/components/dashboard/dashboard-link";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import type { CmsAttentionItem } from "@/types/cms";

export function CmsAttentionQueue({ items }: { items: CmsAttentionItem[] }) {
  return (
    <Card data-testid="cms-attention-queue">
      <CardTitle>Content requiring attention</CardTitle>
      <CardDescription className="mt-1">Read-only queue derived from preview records and validation rules.</CardDescription>
      {items.length === 0 ? (
        <p className="mt-4 text-sm text-jp-muted">No attention items for current filters.</p>
      ) : (
        <ul className="mt-4 divide-y divide-jp-border">
          {items.slice(0, 15).map((item) => (
            <li key={item.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="min-w-0">
                <p className="text-xs font-medium uppercase tracking-wide text-jp-muted">{item.categoryLabel}</p>
                <p className="font-medium text-gray-900">{item.title}</p>
                <p className="text-sm text-jp-muted">{item.description}</p>
              </div>
              <DashboardLink
                href={item.href}
                className="min-h-11 shrink-0 rounded-lg border border-jp-border px-3 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
              >
                {item.linkLabel}
              </DashboardLink>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
