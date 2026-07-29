import type { BaggageDetailsContract } from "../types";

const NOT_PROVIDED = "Not provided by airline";

type BaggageDetailsProps = {
  baggage?: BaggageDetailsContract | null;
  summaryDisplay?: string | null;
  checkedDisplay?: string | null;
  cabinDisplay?: string | null;
};

export function BaggageDetails({ baggage, summaryDisplay, checkedDisplay, cabinDisplay }: BaggageDetailsProps) {
  const checked = baggage?.checked ?? checkedDisplay ?? null;
  const cabin = baggage?.cabin ?? cabinDisplay ?? null;
  const summary = baggage?.summary ?? summaryDisplay ?? null;
  const unavailable = baggage?.unavailable_message ?? (!checked && !cabin && !summary ? NOT_PROVIDED : null);

  return (
    <section data-testid="baggage-details" aria-labelledby="baggage-heading">
      <h3 id="baggage-heading" className="text-sm font-semibold text-jp-text">
        Baggage
      </h3>
      <dl className="mt-2 space-y-2 text-sm">
        {summary ? (
          <div className="flex flex-wrap justify-between gap-2">
            <dt className="text-jp-text-muted">Summary</dt>
            <dd className="text-jp-text">{summary}</dd>
          </div>
        ) : null}
        {checked ? (
          <div className="flex flex-wrap justify-between gap-2">
            <dt className="text-jp-text-muted">Checked baggage</dt>
            <dd className="text-jp-text">{checked}</dd>
          </div>
        ) : null}
        {cabin ? (
          <div className="flex flex-wrap justify-between gap-2">
            <dt className="text-jp-text-muted">Cabin baggage</dt>
            <dd className="text-jp-text">{cabin}</dd>
          </div>
        ) : null}
        {baggage?.passenger_baggage?.map((row, index) => (
          <div key={`pax-bag-${index}`} className="rounded-jp-md border border-jp-border p-2">
            <p className="font-medium text-jp-text">{row.passenger_type ?? "Passenger"}</p>
            {row.checked ? <p className="text-jp-text-muted">Checked: {row.checked}</p> : null}
            {row.cabin ? <p className="text-jp-text-muted">Cabin: {row.cabin}</p> : null}
          </div>
        ))}
        {unavailable && !checked && !cabin && !summary ? (
          <p className="text-jp-text-muted">{unavailable}</p>
        ) : null}
      </dl>
    </section>
  );
}
