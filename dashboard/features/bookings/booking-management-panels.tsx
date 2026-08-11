import type { ReactNode } from "react";
import { MoneyDisplay } from "@/components/ui/money-display";
import { formatDateTime } from "@/lib/format";
import type { BookingManagementDetail } from "@/types/booking";

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
      <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
      <div className="mt-3">{children}</div>
    </section>
  );
}

export function BookingManagementPanels({ detail }: { detail: BookingManagementDetail }) {
  const { summary, passengers, fareSummary, pnrSummary, ticketReadiness, auditMetadata } = detail;

  return (
    <div className="space-y-4" data-testid="booking-management-panels">
      {passengers.length > 0 ? (
        <Section title="Passengers">
          <ul className="space-y-2 text-sm">
            {passengers.map((passenger, index) => (
              <li key={`${passenger.displayName}-${index}`} className="flex justify-between gap-3">
                <span>{passenger.displayName}</span>
                <span className="text-jp-muted capitalize">{passenger.type}</span>
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {fareSummary ? (
        <Section title="Fare breakdown">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Base fare</dt>
              <dd>
                <MoneyDisplay
                  amount={fareSummary.baseFare}
                  currency={fareSummary.currency}
                  currencyStatus={fareSummary.currencyStatus}
                />
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Taxes</dt>
              <dd>
                <MoneyDisplay
                  amount={fareSummary.taxes}
                  currency={fareSummary.currency}
                  currencyStatus={fareSummary.currencyStatus}
                />
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Fees</dt>
              <dd>
                <MoneyDisplay
                  amount={fareSummary.fees}
                  currency={fareSummary.currency}
                  currencyStatus={fareSummary.currencyStatus}
                />
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Markup</dt>
              <dd>
                <MoneyDisplay
                  amount={fareSummary.markup}
                  currency={fareSummary.currency}
                  currencyStatus={fareSummary.currencyStatus}
                />
              </dd>
            </div>
            <div className="flex justify-between gap-3 border-t border-jp-border pt-2 font-semibold">
              <dt>Total</dt>
              <dd>
                <MoneyDisplay
                  amount={fareSummary.total}
                  currency={fareSummary.currency}
                  currencyStatus={fareSummary.currencyStatus}
                />
              </dd>
            </div>
          </dl>
        </Section>
      ) : null}

      {pnrSummary ? (
        <Section title="PNR & supplier">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">PNR</dt>
              <dd className="font-mono text-xs">{pnrSummary.pnr ?? "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Supplier ref</dt>
              <dd>{pnrSummary.supplierReference ?? "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Supplier</dt>
              <dd>{pnrSummary.supplier}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Supplier status</dt>
              <dd className="capitalize">{pnrSummary.supplierStatus.replace(/_/g, " ")}</dd>
            </div>
          </dl>
        </Section>
      ) : null}

      {ticketReadiness ? (
        <Section title="Ticketing">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Status</dt>
              <dd className="capitalize">{ticketReadiness.ticketingStatus}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Ticket records</dt>
              <dd>{ticketReadiness.ticketCount}</dd>
            </div>
          </dl>
        </Section>
      ) : null}

      {auditMetadata ? (
        <Section title="Audit">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Created</dt>
              <dd>{auditMetadata.createdAt ? formatDateTime(auditMetadata.createdAt) : "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Updated</dt>
              <dd>{auditMetadata.updatedAt ? formatDateTime(auditMetadata.updatedAt) : summary.lastUpdated}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Booking ref</dt>
              <dd className="font-mono text-xs">{summary.id}</dd>
            </div>
          </dl>
        </Section>
      ) : null}
    </div>
  );
}
