import type { PassengerFormValues, TravelDocumentRequirement } from "../types";
import { TITLES } from "../utils/passenger-form";

type PassengerCardProps = {
  index: number;
  label: string;
  isLead: boolean;
  passenger: PassengerFormValues;
  documentRequirements: TravelDocumentRequirement;
  nationalIdAllowed: boolean;
  fieldErrors: Record<string, string>;
  onChange: (index: number, field: keyof PassengerFormValues, value: string) => void;
};

export function PassengerCard({
  index,
  label,
  isLead,
  passenger,
  documentRequirements,
  nationalIdAllowed,
  fieldErrors,
  onChange,
}: PassengerCardProps) {
  const showPassport =
    documentRequirements.passport_required || passenger.document_type === "passport";
  const showNationalId =
    nationalIdAllowed && passenger.document_type === "national_id";

  return (
    <fieldset
      className="rounded-jp-lg border border-jp-border bg-jp-surface p-4"
      data-testid={`passenger-card-${index}`}
    >
      <legend className="flex items-center gap-2 px-1 text-jp-sm font-semibold">
        <span>{label}</span>
        {isLead ? (
          <span className="rounded-jp-pill bg-jp-primary-soft px-2 py-0.5 text-jp-xs font-medium text-jp-primary">
            Lead passenger
          </span>
        ) : null}
      </legend>

      <input type="hidden" name={`passengers[${index}][passenger_type]`} value={passenger.passenger_type} />

      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        <label className="text-jp-sm">
          Title <span className="text-red-700">*</span>
          <select
            value={passenger.title}
            onChange={(e) => onChange(index, "title", e.target.value)}
            className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            aria-invalid={Boolean(fieldErrors[`passengers.${index}.title`])}
          >
            {TITLES.map((title) => (
              <option key={title} value={title}>{title}</option>
            ))}
          </select>
          {fieldErrors[`passengers.${index}.title`] ? (
            <p className="mt-1 text-jp-sm text-red-700">{fieldErrors[`passengers.${index}.title`]}</p>
          ) : null}
        </label>

        <label className="text-jp-sm">
          Gender <span className="text-red-700">*</span>
          <select
            value={passenger.gender}
            onChange={(e) => onChange(index, "gender", e.target.value)}
            className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
          >
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </label>

        <label className="text-jp-sm sm:col-span-1">
          First name <span className="text-red-700">*</span>
          <input
            type="text"
            autoComplete={index === 0 ? "given-name" : "off"}
            value={passenger.first_name}
            onChange={(e) => onChange(index, "first_name", e.target.value)}
            className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            aria-invalid={Boolean(fieldErrors[`passengers.${index}.first_name`])}
          />
          {fieldErrors[`passengers.${index}.first_name`] ? (
            <p className="mt-1 text-jp-sm text-red-700">{fieldErrors[`passengers.${index}.first_name`]}</p>
          ) : null}
        </label>

        <label className="text-jp-sm sm:col-span-1">
          Last name <span className="text-red-700">*</span>
          <input
            type="text"
            autoComplete={index === 0 ? "family-name" : "off"}
            value={passenger.last_name}
            onChange={(e) => onChange(index, "last_name", e.target.value)}
            className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
          />
        </label>

        <label className="text-jp-sm">
          Date of birth <span className="text-red-700">*</span>
          <input
            type="date"
            max={new Date().toISOString().slice(0, 10)}
            value={passenger.date_of_birth}
            onChange={(e) => onChange(index, "date_of_birth", e.target.value)}
            className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
          />
          {fieldErrors[`passengers.${index}.date_of_birth`] ? (
            <p className="mt-1 text-jp-sm text-red-700">{fieldErrors[`passengers.${index}.date_of_birth`]}</p>
          ) : null}
        </label>

        <label className="text-jp-sm">
          Nationality {documentRequirements.passport_required ? <span className="text-red-700">*</span> : <span className="text-jp-muted">(optional)</span>}
          <input
            type="text"
            maxLength={2}
            placeholder="PK"
            autoComplete="off"
            value={passenger.nationality}
            onChange={(e) => onChange(index, "nationality", e.target.value.toUpperCase())}
            className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 uppercase"
          />
        </label>

        {nationalIdAllowed ? (
          <label className="text-jp-sm sm:col-span-2">
            Document type <span className="text-red-700">*</span>
            <select
              value={passenger.document_type}
              onChange={(e) => onChange(index, "document_type", e.target.value)}
              className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            >
              <option value="passport">Passport</option>
              <option value="national_id">National ID / CNIC</option>
            </select>
          </label>
        ) : null}

        {showPassport ? (
          <>
            <label className="text-jp-sm">
              Passport number <span className="text-red-700">*</span>
              <input
                type="text"
                autoComplete="off"
                value={passenger.passport_number}
                onChange={(e) => onChange(index, "passport_number", e.target.value)}
                className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
              />
            </label>
            <label className="text-jp-sm">
              Issuing country <span className="text-red-700">*</span>
              <input
                type="text"
                maxLength={2}
                autoComplete="off"
                value={passenger.passport_issuing_country}
                onChange={(e) => onChange(index, "passport_issuing_country", e.target.value.toUpperCase())}
                className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 uppercase"
              />
            </label>
            <label className="text-jp-sm">
              Passport expiry <span className="text-red-700">*</span>
              <input
                type="date"
                value={passenger.passport_expiry_date}
                onChange={(e) => onChange(index, "passport_expiry_date", e.target.value)}
                className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
              />
              {fieldErrors[`passengers.${index}.passport_expiry_date`] ? (
                <p className="mt-1 text-jp-sm text-red-700">{fieldErrors[`passengers.${index}.passport_expiry_date`]}</p>
              ) : null}
            </label>
            <label className="text-jp-sm">
              Passport issue date <span className="text-red-700">*</span>
              <input
                type="date"
                max={new Date().toISOString().slice(0, 10)}
                value={passenger.passport_issue_date}
                onChange={(e) => onChange(index, "passport_issue_date", e.target.value)}
                className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
              />
            </label>
          </>
        ) : null}

        {showNationalId ? (
          <label className="text-jp-sm sm:col-span-2">
            CNIC / NICOP <span className="text-red-700">*</span>
            <input
              type="text"
              autoComplete="off"
              value={passenger.national_id_number}
              onChange={(e) => onChange(index, "national_id_number", e.target.value)}
              className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            />
          </label>
        ) : null}
      </div>
    </fieldset>
  );
}
