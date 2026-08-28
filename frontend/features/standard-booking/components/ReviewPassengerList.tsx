type ReviewPassengerListProps = {
  passengers: Array<Record<string, unknown>>;
  documents: Array<Record<string, unknown>>;
  onEdit?: () => void;
};

const NULLISH = new Set(["null", "undefined", "none", "nil", ""]);

function cleanText(raw: unknown): string {
  if (raw === null || raw === undefined) return "";
  const value = String(raw).trim();
  if (!value || NULLISH.has(value.toLowerCase())) return "";
  return value;
}

function displayTitle(raw: unknown): string {
  return cleanText(raw);
}

function displayGender(raw: unknown): string {
  const value = cleanText(raw);
  if (!value) return "";
  const lower = value.toLowerCase();
  if (lower === "male" || lower === "m") return "Male";
  if (lower === "female" || lower === "f") return "Female";
  if (value === "Male" || value === "Female") return value;
  return "";
}

function typeLabel(type: string, index: number, passengers: Array<Record<string, unknown>>): string {
  const normalized = (type || "adult").toLowerCase();
  const label =
    normalized === "child" ? "Child" : normalized === "infant" ? "Infant" : "Adult";
  const sameTypeIndex =
    passengers
      .slice(0, index + 1)
      .filter((p) => String(p.passenger_type ?? "adult").toLowerCase() === normalized).length;
  return `${label} ${sameTypeIndex}`;
}

function Row({ label, value }: { label: string; value?: string | null }) {
  const cleaned = cleanText(value);
  if (!cleaned) return null;
  return (
    <div className="flex justify-between gap-2 text-jp-sm sm:gap-3">
      <dt className="shrink-0 text-jp-muted">{label}</dt>
      <dd className="min-w-0 text-right font-medium text-jp-text">{cleaned}</dd>
    </div>
  );
}

export function ReviewPassengerList({ passengers, documents, onEdit }: ReviewPassengerListProps) {
  return (
    <div className="space-y-3" data-testid="review-passenger-list">
      {passengers.map((passenger, index) => {
        const title = displayTitle(passenger.title);
        const first = cleanText(passenger.first_name);
        const last = cleanText(passenger.last_name);
        const fullName = [title, first, last].filter(Boolean).join(" ");
        const doc =
          documents.find((d) => {
            const label = String(d.passenger_label ?? "").trim().toLowerCase();
            return label === `${first} ${last}`.trim().toLowerCase();
          }) ?? documents[index];

        return (
          <article
            key={index}
            className="rounded-jp-lg border border-jp-border bg-jp-surface p-3 sm:p-4"
            data-testid="review-traveler-card"
          >
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-jp-primary">
                  {typeLabel(String(passenger.passenger_type ?? "adult"), index, passengers)}
                </p>
                <h3 className="mt-0.5 truncate text-jp-base font-semibold text-jp-text">{fullName || "Traveler"}</h3>
              </div>
              {onEdit ? (
                <button
                  type="button"
                  className="shrink-0 text-jp-xs font-semibold text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
                  onClick={onEdit}
                >
                  Edit
                </button>
              ) : null}
            </div>

            <div className="mt-3 grid gap-3 border-t border-jp-border pt-3 md:grid-cols-2 md:gap-5">
              <section className="min-w-0" data-testid="review-traveler-info-col">
                <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Traveler information</p>
                <dl className="mt-1.5 space-y-1">
                  <Row label="Title" value={title || null} />
                  <Row label="Full name" value={[first, last].filter(Boolean).join(" ") || null} />
                  <Row label="Gender" value={displayGender(passenger.gender) || null} />
                  <Row label="Date of birth" value={cleanText(passenger.date_of_birth) || null} />
                  <Row label="Nationality" value={cleanText(passenger.nationality) || null} />
                </dl>
              </section>
              <section className="min-w-0 border-t border-jp-border pt-3 md:border-l md:border-t-0 md:pl-5 md:pt-0" data-testid="review-document-col">
                <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Travel document</p>
                <dl className="mt-1.5 space-y-1">
                  <Row
                    label="Document type"
                    value={cleanText(passenger.document_type ?? doc?.document_type) || "passport"}
                  />
                  <Row
                    label="Passport"
                    value={
                      cleanText(
                        passenger.passport_number_masked ??
                          doc?.passport_number_masked ??
                          passenger.national_id_masked ??
                          doc?.national_id_masked,
                      ) || null
                    }
                  />
                  <Row
                    label="Issuing country"
                    value={
                      cleanText(passenger.passport_issuing_country ?? doc?.passport_issuing_country) || null
                    }
                  />
                  <Row
                    label="Expiry"
                    value={cleanText(passenger.passport_expiry_date ?? doc?.passport_expiry_date) || null}
                  />
                </dl>
              </section>
            </div>
          </article>
        );
      })}
    </div>
  );
}
