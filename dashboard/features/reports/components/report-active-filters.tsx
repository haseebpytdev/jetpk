import { REPORT_DATE_PRESET_LABELS } from "@/lib/reports/date-presets";
import type { ReportsQuery } from "@/types/report";

type FilterChip = { key: string; label: string; value: string };

function activeChips(query: ReportsQuery): FilterChip[] {
  const chips: FilterChip[] = [
    { key: "period", label: "Period", value: `${REPORT_DATE_PRESET_LABELS[query.datePreset]} (${query.startDate} — ${query.endDate})` },
    { key: "currency", label: "Currency", value: query.currency },
  ];
  if (query.comparison !== "none") chips.push({ key: "comparison", label: "Comparison", value: query.comparison.replace(/_/g, " ") });
  if (query.channel) chips.push({ key: "channel", label: "Channel", value: query.channel });
  if (query.supplier) chips.push({ key: "supplier", label: "Supplier", value: query.supplier });
  if (query.airline) chips.push({ key: "airline", label: "Airline", value: query.airline });
  if (query.agent) chips.push({ key: "agent", label: "Agent", value: query.agent });
  if (query.route) chips.push({ key: "route", label: "Route", value: query.route });
  if (query.bookingStatus !== "all") chips.push({ key: "bookingStatus", label: "Booking status", value: query.bookingStatus });
  if (query.paymentStatus !== "all") chips.push({ key: "paymentStatus", label: "Payment status", value: query.paymentStatus });
  if (query.ticketStatus !== "all") chips.push({ key: "ticketStatus", label: "Ticket status", value: query.ticketStatus });
  if (query.fulfilmentStatus !== "all") chips.push({ key: "fulfilmentStatus", label: "Fulfilment status", value: query.fulfilmentStatus });
  return chips;
}

export function ReportActiveFilters({ query }: { query: ReportsQuery }) {
  const chips = activeChips(query);
  return (
    <section aria-label="Active report filters" data-testid="reports-active-filters" className="rounded-xl border border-jp-border bg-gray-50 px-3 py-2">
      <details className="group md:open">
        <summary className="cursor-pointer text-sm font-medium text-gray-900 marker:content-none md:hidden">
          Active filters ({chips.length})
        </summary>
        <ul className="mt-2 flex flex-wrap gap-2 md:mt-0">
          {chips.map((chip) => (
            <li key={chip.key}>
              <span className="inline-flex max-w-full items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs ring-1 ring-jp-border">
                <span className="font-medium text-jp-muted">{chip.label}:</span>
                <span className="truncate">{chip.value}</span>
              </span>
            </li>
          ))}
        </ul>
      </details>
    </section>
  );
}
