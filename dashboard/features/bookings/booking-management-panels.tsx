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
  const {
    summary,
    passengers,
    fareSummary,
    pnrSummary,
    ticketReadiness,
    auditMetadata,
    statusTimeline,
    internalNotes,
    communications,
    documents,
  } = detail;

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

      {statusTimeline.length > 0 ? (
        <Section title="Status timeline">
          <ol className="space-y-3 text-sm" data-testid="booking-status-timeline">
            {statusTimeline.map((entry, index) => (
              <li key={`${entry.occurredAt}-${index}`} className="border-l-2 border-jp-border pl-3">
                <p className="font-medium capitalize">{entry.summary || entry.eventType.replace(/_/g, " ")}</p>
                <p className="text-xs text-jp-muted">
                  {entry.occurredAt ? formatDateTime(entry.occurredAt) : "—"} · {entry.actorName}
                </p>
                {entry.note ? <p className="mt-1 text-jp-muted">{entry.note}</p> : null}
              </li>
            ))}
          </ol>
        </Section>
      ) : null}

      {internalNotes.length > 0 ? (
        <Section title="Internal notes">
          <ul className="space-y-3 text-sm" data-testid="booking-internal-notes">
            {internalNotes.map((note, index) => (
              <li key={`${note.createdAt}-${index}`} className="rounded-xl bg-gray-50 p-3">
                <p className="text-xs text-jp-muted">
                  {note.createdAt ? formatDateTime(note.createdAt) : "—"} · {note.authorName}
                  {note.customerVisible ? " · customer visible" : ""}
                </p>
                <p className="mt-1 whitespace-pre-wrap">{note.note}</p>
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {communications.length > 0 ? (
        <Section title="Communications">
          <ul className="space-y-3 text-sm" data-testid="booking-communications">
            {communications.map((entry, index) => (
              <li key={`${entry.sentAt}-${index}`} className="flex flex-col gap-1">
                <p className="font-medium capitalize">
                  {entry.event.replace(/_/g, " ")} via {entry.channel}
                </p>
                <p className="text-xs text-jp-muted">
                  {entry.sentAt ? formatDateTime(entry.sentAt) : "—"} · {entry.status} · {entry.recipient}
                </p>
                {entry.subject ? <p className="text-jp-muted">{entry.subject}</p> : null}
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {documents.length > 0 ? (
        <Section title="Documents">
          <ul className="space-y-2 text-sm" data-testid="booking-documents">
            {documents.map((document) => (
              <li key={document.documentId} className="flex justify-between gap-3">
                <span>{document.title}</span>
                <span className="text-xs text-jp-muted capitalize">
                  {document.documentType.replace(/_/g, " ")} · {document.status}
                </span>
              </li>
            ))}
          </ul>
          <p className="mt-3 text-xs text-jp-muted">Document downloads remain on the Laravel booking workspace.</p>
        </Section>
      ) : null}
    </div>
  );
}
