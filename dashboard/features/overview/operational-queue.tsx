"use client";

import { DashboardLink } from "@/components/dashboard/dashboard-link";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { dashboardHrefForOperationalQueue } from "@/lib/overview-route-map";
import type { ActionCard } from "@/types/dashboard";

const toneRing: Record<string, string> = {
  amber: "border-l-amber-500",
  violet: "border-l-violet-500",
  emerald: "border-l-emerald-500",
  blue: "border-l-blue-500",
  red: "border-l-red-500",
};

export function OperationalQueueGrid({ cards }: { cards: ActionCard[] }) {
  if (cards.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="ops-queue-heading">
      <h2 id="ops-queue-heading" className="sr-only">
        Operational action queue
      </h2>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        {cards.map((card) => {
          const href = dashboardHrefForOperationalQueue(card);
          const showCta = card.count > 0 && href !== null;

          return (
            <Card key={card.key} className={`border-l-4 ${toneRing[card.tone] ?? "border-l-gray-300"}`}>
              <CardTitle>{card.label}</CardTitle>
              <p className="mt-2 font-display text-3xl font-bold tabular-nums text-gray-900">{card.count}</p>
              <CardDescription className="mt-2">{card.helper}</CardDescription>
              {showCta ? (
                <DashboardLink
                  href={href}
                  data-testid={`ops-queue-cta-${card.key}`}
                  className="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-jp-border bg-white px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50 sm:w-auto"
                >
                  {card.cta}
                </DashboardLink>
              ) : null}
            </Card>
          );
        })}
      </div>
    </section>
  );
}
