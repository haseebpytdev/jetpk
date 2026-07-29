import { ReviewPassengerList } from "../components/ReviewPassengerList";

type PassengerSummaryProps = {
  passengers: Array<Record<string, unknown>>;
  tickets?: Array<Record<string, unknown>>;
};

export function PassengerSummary({ passengers, tickets = [] }: PassengerSummaryProps) {
  const documents = passengers.map((passenger, index) => ({
    passenger_label: `${passenger.first_name ?? ""} ${passenger.last_name ?? ""}`.trim(),
    document_type: passenger.document_type,
    passport_number_masked: passenger.passport_number_masked,
    national_id_masked: passenger.national_id_masked,
    key: index,
  }));

  return (
    <div className="space-y-4" data-testid="passenger-summary">
      <ReviewPassengerList passengers={passengers} documents={documents} />
      {tickets.length > 0 ? (
        <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
          <h2 className="text-jp-base font-semibold text-jp-text">Ticket numbers</h2>
          <ul className="mt-3 space-y-2 text-jp-sm">
            {tickets.map((ticket, index) => (
              <li key={index} data-testid="ticket-number-row">
                {(ticket.passenger_name as string) ?? "Passenger"} · {(ticket.ticket_number as string) ?? "—"}
              </li>
            ))}
          </ul>
        </article>
      ) : null}
    </div>
  );
}
