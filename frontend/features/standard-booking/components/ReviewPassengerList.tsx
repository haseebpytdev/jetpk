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
  if (lower === "male" || lower === "female") return value;
  // Already friendly from API (Male/Female)
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
    <div className="flex justify-between gap-3 text-jp-sm">
      <dt className="text-jp-muted">{label}</dt>
      <dd className="text-right font-medium text-jp-text">{cleaned}</dd>
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
            className="rounded-jp-lg border border-jp-border bg-jp-surface p-4"
            data-testid="review-traveler-card"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-jp-primary">
                  {typeLabel(String(passenger.passenger_type ?? "adult"), index, passengers)}
                </p>
                <h3 className="mt-1 text-jp-base font-semibold text-jp-text">{fullName || "Traveler"}</h3>
              </div>
              {onEdit ? (
                <button
                  type="button"
                  className="text-jp-xs font-semibold text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
                  onClick={onEdit}
                >
                  Edit
                </button>
              ) : null}
            </div>
            <dl className="mt-3 space-y-1.5">
              <Row label="Title" value={title || null} />
              <Row label="Gender" value={displayGender(passenger.gender) || null} />
              <Row label="Date of birth" value={cleanText(passenger.date_of_birth) || null} />
              <Row label="Nationality" value={cleanText(passenger.nationality) || null} />
            </dl>
            <div className="mt-3 border-t border-jp-border pt-3">
              <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Travel document</p>
              <dl className="mt-2 space-y-1.5">
                <Row
                  label="Document"
                  value={cleanText(passenger.document_type ?? doc?.document_type) || "passport"}
                />
                <Row
                  label="Number"
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
            </div>
          </article>
        );
      })}
    </div>
  );
}
