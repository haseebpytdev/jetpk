type ReviewPassengerListProps = {
  passengers: Array<Record<string, unknown>>;
  documents: Array<Record<string, unknown>>;
};

export function ReviewPassengerList({ passengers, documents }: ReviewPassengerListProps) {
  return (
    <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="review-passenger-list">
      <h2 className="text-jp-base font-semibold">Passengers</h2>
      <ul className="mt-3 space-y-3">
        {passengers.map((passenger, index) => (
          <li key={index} className="rounded-jp-md border border-jp-border p-3 text-jp-sm">
            <p className="font-semibold text-jp-text">
              {(passenger.title as string) ?? ""} {(passenger.first_name as string) ?? ""} {(passenger.last_name as string) ?? ""}
            </p>
            <p className="text-jp-muted capitalize">{(passenger.passenger_type as string) ?? "adult"}</p>
          </li>
        ))}
      </ul>
      {documents.length > 0 ? (
        <div className="mt-4">
          <h3 className="text-jp-sm font-semibold">Travel documents</h3>
          <ul className="mt-2 space-y-1 text-jp-sm text-jp-muted">
            {documents.map((doc, index) => (
              <li key={index} data-testid="masked-document">
                {(doc.passenger_label as string) ?? "Passenger"} · {(doc.document_type as string) ?? "document"}
                {(doc.passport_number_masked as string) ? ` · ${doc.passport_number_masked as string}` : ""}
                {(doc.national_id_masked as string) ? ` · ${doc.national_id_masked as string}` : ""}
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </article>
  );
}
