import type { BaggageDetailsContract } from "../types";

const NOT_PROVIDED = "Not provided by airline";

type BaggageDetailsProps = {
  baggage?: BaggageDetailsContract | null;
  summaryDisplay?: string | null;
  checkedDisplay?: string | null;
  cabinDisplay?: string | null;
  routeLabel?: string | null;
};

function BagCard({
  title,
  value,
  tone,
}: {
  title: string;
  value: string;
  tone: "cabin" | "checked";
}) {
  const toneClass =
    tone === "cabin"
      ? "border-jp-primary/15 bg-jp-primary/[0.04]"
      : "border-jp-border bg-jp-surface-muted/80";

  return (
    <div className={`rounded-jp-md border px-3 py-3 ${toneClass}`} data-testid={`baggage-card-${tone}`}>
      <p className="text-[10px] font-semibold uppercase tracking-wide text-jp-text-muted">{title}</p>
      <p className="mt-1 text-sm font-semibold leading-snug text-jp-text">{value}</p>
    </div>
  );
}

function PassengerBlock({
  passengerType,
  cabin,
  checked,
}: {
  passengerType: string;
  cabin?: string | null;
  checked?: string | null;
}) {
  if (!cabin && !checked) return null;

  return (
    <div className="space-y-2.5" data-testid="baggage-passenger-block">
      <p className="text-[11px] font-semibold uppercase tracking-wide text-jp-text-muted">{passengerType}</p>
      <div className="grid gap-2.5 sm:grid-cols-2">
        {cabin ? <BagCard title="Cabin bag" value={cabin} tone="cabin" /> : null}
        {checked ? <BagCard title="Checked bag" value={checked} tone="checked" /> : null}
      </div>
    </div>
  );
}

export function BaggageDetails({
  baggage,
  summaryDisplay,
  checkedDisplay,
  cabinDisplay,
  routeLabel,
}: BaggageDetailsProps) {
  const checked = baggage?.checked ?? checkedDisplay ?? null;
  const cabin = baggage?.cabin ?? cabinDisplay ?? null;
  const summary = baggage?.summary ?? summaryDisplay ?? null;
  const passengerRows = (baggage?.passenger_baggage ?? []).filter((row) => row.cabin || row.checked);
  const segmentRows = (baggage?.segment_baggage ?? []).filter((row) => row.cabin || row.checked || row.route);
  const unavailable =
    baggage?.unavailable_message ??
    (!checked && !cabin && !summary && passengerRows.length === 0 && segmentRows.length === 0 ? NOT_PROVIDED : null);

  return (
    <section data-testid="baggage-details" aria-labelledby="baggage-heading">
      <h3 id="baggage-heading" className="sr-only">
        Baggage policy
      </h3>

      {routeLabel ? (
        <div
          className="mb-3 flex items-center gap-2 rounded-jp-md bg-jp-surface-muted px-3 py-2 text-sm font-medium text-jp-text"
          data-testid="baggage-route-header"
        >
          <span className="text-jp-primary" aria-hidden>
            ✈
          </span>
          <span>{routeLabel}</span>
        </div>
      ) : null}

      {segmentRows.length > 0 ? (
        <div className="space-y-4">
          {segmentRows.map((row, index) => (
            <div key={`seg-bag-${index}`} className="space-y-2.5">
              {row.route ? (
                <p className="text-xs font-medium text-jp-text-muted" data-testid="baggage-segment-route">
                  {row.route}
                </p>
              ) : null}
              <PassengerBlock
                passengerType="ADULT"
                cabin={row.cabin}
                checked={row.checked}
              />
            </div>
          ))}
        </div>
      ) : passengerRows.length > 0 ? (
        <div className="space-y-4">
          {passengerRows.map((row, index) => (
            <PassengerBlock
              key={`pax-bag-${index}`}
              passengerType={(row.passenger_type ?? "ADULT").toUpperCase()}
              cabin={row.cabin}
              checked={row.checked}
            />
          ))}
        </div>
      ) : cabin || checked ? (
        <PassengerBlock passengerType="ADULT" cabin={cabin} checked={checked} />
      ) : summary ? (
        <div className="rounded-jp-md border border-jp-primary/15 bg-jp-primary/[0.04] px-3 py-3">
          <p className="text-[10px] font-semibold uppercase tracking-wide text-jp-text-muted">Included allowance</p>
          <p className="mt-1 text-sm font-semibold text-jp-text">{summary}</p>
        </div>
      ) : unavailable ? (
        <p className="text-sm text-jp-text-muted">{unavailable}</p>
      ) : null}
    </section>
  );
}
