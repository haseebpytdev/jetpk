"use client";

import dynamic from "next/dynamic";
import { useState } from "react";
import type { PassengerFormValues, TravelDocumentRequirement } from "../types";
import { TITLES } from "../utils/passenger-form";

const DocumentReader = dynamic(
  () => import("../document-reader/components/DocumentReader").then((mod) => mod.DocumentReader),
  { ssr: false, loading: () => null },
);

type PassengerCardProps = {
  index: number;
  label: string;
  isLead: boolean;
  passenger: PassengerFormValues;
  documentRequirements: TravelDocumentRequirement;
  nationalIdAllowed: boolean;
  fieldErrors: Record<string, string>;
  onChange: (index: number, field: keyof PassengerFormValues, value: string) => void;
  onReplacePassenger?: (index: number, next: PassengerFormValues) => void;
  savedTravelers?: Array<{
    id: number;
    first_name: string;
    last_name: string;
    document_number_masked?: string | null;
    is_default?: boolean;
  }>;
  selectedSavedTravelerId?: number | null;
  onSelectSavedTraveler?: (travelerId: number | null) => void;
};

const fieldClass = "mt-1 h-10 w-full rounded-jp-md border border-jp-border bg-white px-3 text-sm text-jp-text shadow-sm outline-none transition-colors focus:border-jp-primary focus:ring-2 focus:ring-jp-primary/20 aria-[invalid=true]:border-red-600 aria-[invalid=true]:ring-1 aria-[invalid=true]:ring-red-200";

export function PassengerCard({
  index,
  label,
  isLead,
  passenger,
  documentRequirements,
  nationalIdAllowed,
  fieldErrors,
  onChange,
  onReplacePassenger,
  savedTravelers = [],
  selectedSavedTravelerId = null,
  onSelectSavedTraveler,
}: PassengerCardProps) {
  const [passportAutofillOpen, setPassportAutofillOpen] = useState(false);
  const showPassport =
    documentRequirements.passport_required || passenger.document_type === "passport";
  const showNationalId =
    nationalIdAllowed && passenger.document_type === "national_id";

  return (
    <fieldset
      className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-card sm:p-5"
      data-testid={`passenger-card-${index}`}
    >
      <legend className="sr-only">
        {label}
        {isLead ? " (Lead passenger)" : ""}
      </legend>
      <div className="mb-2 flex flex-wrap items-center gap-2" data-testid={`passenger-card-header-${index}`}>
        <h2 className="text-sm font-semibold text-jp-text">{label}</h2>
        {isLead ? (
          <span className="rounded-jp-md bg-jp-primary/10 px-2 py-0.5 text-[11px] font-semibold text-jp-primary">
            Lead passenger
          </span>
        ) : null}
      </div>
      <p className="text-xs text-jp-muted">Enter details exactly as shown on the passport.</p>

      {savedTravelers.length > 0 && onSelectSavedTraveler ? (
        <label className="mt-3 block text-jp-sm" data-testid={`saved-traveler-picker-${index}`}>
          Use saved traveler
          <select
            className={fieldClass}
            value={selectedSavedTravelerId ?? ""}
            onChange={(e) => {
              const raw = e.target.value;
              onSelectSavedTraveler(raw === "" ? null : Number(raw));
            }}
          >
            <option value="">Enter manually</option>
            {savedTravelers.map((traveler) => (
              <option key={traveler.id} value={traveler.id}>
                {traveler.last_name}, {traveler.first_name}
                {traveler.document_number_masked ? ` · ${traveler.document_number_masked}` : ""}
                {traveler.is_default ? " (default)" : ""}
              </option>
            ))}
          </select>
        </label>
      ) : null}

      <input type="hidden" name={`passengers[${index}][passenger_type]`} value={passenger.passenger_type} />

      <div className="mt-4 grid gap-x-5 gap-y-3.5 md:grid-cols-2">
        <div className="border-b border-jp-border-soft pb-2 md:col-span-2">
          <h3 className="text-sm font-semibold text-jp-text">Personal information</h3>
        </div>
        <label className="text-jp-sm">
          Title <span className="text-red-700">*</span>
          <select
            value={passenger.title}
            onChange={(e) => onChange(index, "title", e.target.value)}
            className={fieldClass}
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
            className={fieldClass}
          >
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </label>

        <label className="text-jp-sm md:col-span-1">
          First name <span className="text-red-700">*</span>
          <input
            type="text"
            autoComplete={index === 0 ? "given-name" : "off"}
            value={passenger.first_name}
            onChange={(e) => onChange(index, "first_name", e.target.value)}
            className={fieldClass}
            aria-invalid={Boolean(fieldErrors[`passengers.${index}.first_name`])}
          />
          {fieldErrors[`passengers.${index}.first_name`] ? (
            <p className="mt-1 text-jp-sm text-red-700">{fieldErrors[`passengers.${index}.first_name`]}</p>
          ) : null}
        </label>

        <label className="text-jp-sm md:col-span-1">
          Last name <span className="text-red-700">*</span>
          <input
            type="text"
            autoComplete={index === 0 ? "family-name" : "off"}
            value={passenger.last_name}
            onChange={(e) => onChange(index, "last_name", e.target.value)}
            className={fieldClass}
          />
        </label>

        <label className="text-jp-sm">
          Date of birth <span className="text-red-700">*</span>
          <input
            type="date"
            max={new Date().toISOString().slice(0, 10)}
            value={passenger.date_of_birth}
            onChange={(e) => onChange(index, "date_of_birth", e.target.value)}
            className={fieldClass}
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
            className={`${fieldClass} uppercase`}
          />
        </label>

        <div className="mt-1 border-b border-jp-border-soft pb-2 md:col-span-2">
          <h3 className="text-sm font-semibold text-jp-text">Travel document</h3>
        </div>

        {showPassport && onReplacePassenger ? (
          passportAutofillOpen ? (
            <DocumentReader
              passengerIndex={index}
              passenger={passenger}
              onApply={(next) => onReplacePassenger(index, next)}
            />
          ) : (
            <div className="md:col-span-2">
              <button
                type="button"
                className="text-sm font-medium text-jp-primary underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
                data-testid={`passport-autofill-open-${index}`}
                onClick={() => setPassportAutofillOpen(true)}
              >
                Autofill from passport
              </button>
            </div>
          )
        ) : null}

        {nationalIdAllowed ? (
          <label className="text-jp-sm md:col-span-2">
            Document type <span className="text-red-700">*</span>
            <select
              value={passenger.document_type}
              onChange={(e) => onChange(index, "document_type", e.target.value)}
              className={fieldClass}
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
                className={fieldClass}
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
                className={`${fieldClass} uppercase`}
              />
            </label>
            <label className="text-jp-sm">
              Passport expiry <span className="text-red-700">*</span>
              <input
                type="date"
                value={passenger.passport_expiry_date}
                onChange={(e) => onChange(index, "passport_expiry_date", e.target.value)}
                className={fieldClass}
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
                className={fieldClass}
              />
            </label>
          </>
        ) : null}

        {showNationalId ? (
          <label className="text-jp-sm md:col-span-2">
            CNIC / NICOP <span className="text-red-700">*</span>
            <input
              type="text"
              autoComplete="off"
              value={passenger.national_id_number}
              onChange={(e) => onChange(index, "national_id_number", e.target.value)}
              className={fieldClass}
            />
          </label>
        ) : null}
      </div>
    </fieldset>
  );
}
