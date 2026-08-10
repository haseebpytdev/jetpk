"use client";

import { Button } from "@/components/ui/button";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { laravelRouteUrl } from "@/lib/laravel-route-url";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import type { ActionCard } from "@/types/dashboard";

const toneRing: Record<string, string> = {
  amber: "border-l-amber-500",
  violet: "border-l-violet-500",
  emerald: "border-l-emerald-500",
  blue: "border-l-blue-500",
  red: "border-l-red-500",
};

export function OperationalQueueGrid({ cards }: { cards: ActionCard[] }) {
  const isLive = useDashboardLiveMode();

  return (
    <section aria-labelledby="ops-queue-heading">
      <div className="mb-2 flex items-baseline justify-between gap-2">
        <h2 id="ops-queue-heading" className="text-sm font-semibold text-gray-900">
          Needs attention
        </h2>
        {isLive ? <span className="text-xs text-jp-muted">Actionable workload</span> : null}
      </div>
      {cards.length === 0 ? (
        <Card className="border-dashed p-4">
          <p className="text-sm text-jp-muted">
            {isLive ? "No operational items need attention right now." : "No preview queue items."}
          </p>
        </Card>
      ) : (
        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
          {cards.map((card) => {
            const href = laravelRouteUrl(
              card.laravelRoute,
              card.queue ? { queue: card.queue } : undefined,
            );

            return (
              <Card key={card.key} className={`border-l-4 p-3 ${toneRing[card.tone] ?? "border-l-gray-300"}`}>
                <CardTitle className="text-sm">{card.label}</CardTitle>
                <p className="mt-1 font-display text-2xl font-bold tabular-nums text-gray-900">{card.count}</p>
                <CardDescription className="mt-1 text-xs">{card.helper}</CardDescription>
                {isLive ? (
                  <a
                    href={href}
                    className="mt-3 inline-flex min-h-9 w-full items-center justify-center rounded-lg border border-jp-border bg-white px-3 py-1.5 text-sm font-medium text-jp-text hover:bg-gray-50 sm:w-auto"
                  >
                    {card.cta}
                  </a>
                ) : (
                  <Button
                    className="mt-3 w-full sm:w-auto"
                    variant="secondary"
                    size="sm"
                    type="button"
                    onClick={() => alert(`Preview only — ${href}`)}
                  >
                    {card.cta}
                  </Button>
                )}
              </Card>
            );
          })}
        </div>
      )}
    </section>
  );
}
