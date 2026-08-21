"use client";

import { useId, useRef, useState } from "react";
import type { PassengerFormValues } from "../../types";
import {
  applyConfirmedExtraction,
  planExtractedFieldMerge,
  type FieldConflict,
} from "../applyExtractedFields";
import type { MrzExtractedFields, MrzParseResult } from "../mrz/parseMrz";
import { scanDocumentClientSide } from "../ocr/scanDocumentClientSide";

type DocumentReaderProps = {
  passengerIndex: number;
  passenger: PassengerFormValues;
  onApply: (next: PassengerFormValues) => void;
};

type ReaderPhase = "idle" | "processing" | "preview" | "error";

const VERIFY_COPY = "Please verify extracted details against the passport before continuing.";

const FIELD_LABELS: Record<keyof MrzExtractedFields, string> = {
  last_name: "Surname",
  first_name: "Given names",
  passport_number: "Passport number",
  nationality: "Nationality",
  date_of_birth: "Date of birth",
  gender: "Sex",
  passport_expiry_date: "Expiry date",
  passport_issuing_country: "Issuing country",
  passport_issue_date: "Issue date",
};

export function DocumentReader({ passengerIndex, passenger, onApply }: DocumentReaderProps) {
  const pasteId = useId();
  const fileRef = useRef<HTMLInputElement>(null);
  const [phase, setPhase] = useState<ReaderPhase>("idle");
  const [result, setResult] = useState<MrzParseResult | null>(null);
  const [pasteOpen, setPasteOpen] = useState(false);
  const [pasteText, setPasteText] = useState("");
  const [conflicts, setConflicts] = useState<FieldConflict[]>([]);
  const [choices, setChoices] = useState<
    Partial<Record<keyof MrzExtractedFields, "keep" | "use_extracted">>
  >({});
  const [statusMessage, setStatusMessage] = useState<string | null>(null);

  const runParse = async (source: Parameters<typeof scanDocumentClientSide>[0]) => {
    setPhase("processing");
    setStatusMessage(null);
    setResult(null);
    setConflicts([]);
    setChoices({});
    try {
      const parsed = await scanDocumentClientSide(source);
      setResult(parsed);
      if (!parsed.ok && Object.keys(parsed.fields).length === 0) {
        setPhase("error");
        setStatusMessage(parsed.warnings[0] ?? "Could not extract document details.");
        return;
      }
      const plan = planExtractedFieldMerge(passenger, parsed.fields);
      setConflicts(plan.conflicts);
      setPhase("preview");
    } catch {
      setPhase("error");
      setStatusMessage("Document reading failed in the browser. Try pasting the MRZ lines.");
    }
  };

  const handleFile = (file: File | undefined) => {
    if (!file) return;
    void runParse({ kind: "image", file });
  };

  const handleConfirm = () => {
    if (!result) return;
    const unresolved = conflicts.filter((conflict) => choices[conflict.field] == null);
    if (unresolved.length > 0) {
      setStatusMessage("Resolve each highlighted field before applying extracted details.");
      return;
    }
    const resolvedChoices: Partial<Record<keyof MrzExtractedFields, "keep" | "use_extracted">> = {
      ...choices,
    };
    for (const conflict of conflicts) {
      resolvedChoices[conflict.field] = choices[conflict.field] ?? "keep";
    }
    const next = applyConfirmedExtraction(passenger, result.fields, resolvedChoices);
    onApply(next);
    setPhase("idle");
    setResult(null);
    setConflicts([]);
    setChoices({});
    setPasteOpen(false);
    setPasteText("");
    setStatusMessage("Extracted details applied. Please verify against the passport before continuing.");
  };

  return (
    <div
      className="sm:col-span-2 rounded-jp-md border border-dashed border-jp-border bg-jp-page/60 p-3"
      data-testid={`document-reader-${passengerIndex}`}
      data-architecture="CLIENT_SIDE"
    >
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h4 className="text-sm font-semibold text-jp-text">Document reader</h4>
          <p className="mt-0.5 text-xs text-jp-muted">
            Scan or upload stays on this device. Passport images are never sent to cloud OCR.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            className="rounded-jp-md border border-jp-border bg-white px-3 py-1.5 text-xs font-semibold text-jp-text hover:border-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
            data-testid={`document-reader-scan-${passengerIndex}`}
            onClick={() => fileRef.current?.click()}
            disabled={phase === "processing"}
          >
            Scan / Upload
          </button>
          <button
            type="button"
            className="rounded-jp-md border border-jp-border bg-white px-3 py-1.5 text-xs font-semibold text-jp-text hover:border-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
            data-testid={`document-reader-paste-toggle-${passengerIndex}`}
            onClick={() => setPasteOpen((open) => !open)}
            disabled={phase === "processing"}
          >
            Paste MRZ
          </button>
        </div>
      </div>

      <input
        ref={fileRef}
        type="file"
        accept="image/*,text/plain,.txt"
        capture="environment"
        className="hidden"
        data-testid={`document-reader-file-${passengerIndex}`}
        onChange={(event) => {
          handleFile(event.target.files?.[0]);
          event.target.value = "";
        }}
      />

      {pasteOpen ? (
        <div className="mt-3 space-y-2">
          <label className="block text-xs font-medium text-jp-muted" htmlFor={pasteId}>
            Paste the two MRZ lines from the passport data page
          </label>
          <textarea
            id={pasteId}
            value={pasteText}
            onChange={(event) => setPasteText(event.target.value)}
            rows={3}
            spellCheck={false}
            className="w-full rounded-jp-md border border-jp-border bg-white px-3 py-2 font-mono text-xs text-jp-text focus:border-jp-primary focus:ring-2 focus:ring-jp-primary/20"
            data-testid={`document-reader-paste-${passengerIndex}`}
            placeholder={"P<UTO...\nL898902C3..."}
          />
          <button
            type="button"
            className="rounded-jp-md bg-jp-primary px-3 py-1.5 text-xs font-semibold text-white focus-visible:outline-none focus-visible:shadow-jp-focus"
            data-testid={`document-reader-parse-paste-${passengerIndex}`}
            onClick={() => void runParse({ kind: "text", text: pasteText })}
            disabled={phase === "processing" || !pasteText.trim()}
          >
            Read MRZ
          </button>
        </div>
      ) : null}

      {phase === "processing" ? (
        <p className="mt-3 text-sm text-jp-muted" role="status" data-testid={`document-reader-processing-${passengerIndex}`}>
          Reading document on this device…
        </p>
      ) : null}

      {phase === "error" && statusMessage ? (
        <p className="mt-3 text-sm text-red-700" role="alert" data-testid={`document-reader-error-${passengerIndex}`}>
          {statusMessage}
        </p>
      ) : null}

      {phase === "preview" && result ? (
        <div className="mt-3 space-y-3" data-testid={`document-reader-preview-${passengerIndex}`}>
          <p className="rounded-jp-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950" role="status">
            {VERIFY_COPY}
          </p>

          {!result.checkDigitsValid ? (
            <p className="text-xs font-medium text-amber-800" data-testid={`document-reader-checkdigit-warning-${passengerIndex}`}>
              MRZ check digits did not validate. Review every field carefully.
            </p>
          ) : null}

          {result.warnings.length > 0 ? (
            <ul className="list-disc space-y-1 pl-4 text-xs text-jp-muted" data-testid={`document-reader-warnings-${passengerIndex}`}>
              {result.warnings.map((warning) => (
                <li key={warning}>{warning}</li>
              ))}
            </ul>
          ) : null}

          <dl className="grid gap-2 sm:grid-cols-2">
            {(Object.keys(result.fields) as Array<keyof MrzExtractedFields>).map((field) => {
              const value = result.fields[field];
              if (value == null || value === "") return null;
              const conf = result.confidence.find((row) => row.field === field)?.confidence ?? "medium";
              return (
                <div key={field} className="rounded-jp-md border border-jp-border-soft bg-white px-3 py-2">
                  <dt className="text-[11px] font-semibold uppercase tracking-wide text-jp-muted">
                    {FIELD_LABELS[field]}
                    <span className="ml-2 font-normal normal-case text-jp-muted">({conf})</span>
                  </dt>
                  <dd className="mt-0.5 text-sm font-medium text-jp-text">{String(value)}</dd>
                </div>
              );
            })}
          </dl>

          {conflicts.length > 0 ? (
            <div
              className="space-y-2 rounded-jp-md border border-jp-border bg-white p-3"
              data-testid={`document-reader-conflicts-${passengerIndex}`}
            >
              <p className="text-xs font-semibold text-jp-text">
                Some fields already have values. Choose keep or replace — nothing is overwritten silently.
              </p>
              {conflicts.map((conflict) => (
                <div
                  key={conflict.field}
                  className="grid gap-2 border-t border-jp-border-soft pt-2 sm:grid-cols-[1fr_auto]"
                  data-testid={`document-reader-conflict-${passengerIndex}-${conflict.field}`}
                >
                  <div>
                    <p className="text-xs font-medium text-jp-text">{FIELD_LABELS[conflict.field]}</p>
                    <p className="text-xs text-jp-muted">Current: {conflict.existing}</p>
                    <p className="text-xs text-jp-muted">Extracted: {conflict.extracted}</p>
                  </div>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      className={`rounded-jp-md border px-2 py-1 text-xs ${choices[conflict.field] === "keep" ? "border-jp-primary bg-jp-primary-soft text-jp-primary" : "border-jp-border"}`}
                      onClick={() => setChoices((current) => ({ ...current, [conflict.field]: "keep" }))}
                    >
                      Keep
                    </button>
                    <button
                      type="button"
                      className={`rounded-jp-md border px-2 py-1 text-xs ${choices[conflict.field] === "use_extracted" ? "border-jp-primary bg-jp-primary-soft text-jp-primary" : "border-jp-border"}`}
                      onClick={() => setChoices((current) => ({ ...current, [conflict.field]: "use_extracted" }))}
                    >
                      Use extracted
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : null}

          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className="rounded-jp-md bg-jp-primary px-3 py-2 text-xs font-semibold text-white focus-visible:outline-none focus-visible:shadow-jp-focus"
              data-testid={`document-reader-confirm-${passengerIndex}`}
              onClick={handleConfirm}
            >
              Confirm and fill form
            </button>
            <button
              type="button"
              className="rounded-jp-md border border-jp-border bg-white px-3 py-2 text-xs font-semibold text-jp-text"
              data-testid={`document-reader-discard-${passengerIndex}`}
              onClick={() => {
                setPhase("idle");
                setResult(null);
                setConflicts([]);
              }}
            >
              Discard
            </button>
          </div>
        </div>
      ) : null}

      {phase === "idle" && statusMessage ? (
        <p className="mt-3 text-xs text-jp-muted" role="status" data-testid={`document-reader-status-${passengerIndex}`}>
          {statusMessage}
        </p>
      ) : null}

      <p className="mt-3 text-[11px] text-jp-muted">{VERIFY_COPY}</p>
    </div>
  );
}
