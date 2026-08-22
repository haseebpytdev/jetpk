"use client";

import { useEffect, useRef, useState } from "react";
import type { PassengerFormValues } from "../../types";
import {
  applyConfirmedExtraction,
  planExtractedFieldMerge,
  type FieldConflict,
} from "../applyExtractedFields";
import type { MrzExtractedFields, MrzParseResult } from "../mrz/parseMrz";
import { scanDocumentClientSide } from "../ocr/scanDocumentClientSide";
import { applyTitleAssistance } from "../titleFromPassport";

type DocumentReaderProps = {
  passengerIndex: number;
  passenger: PassengerFormValues;
  onApply: (next: PassengerFormValues) => void;
};

type ReaderPhase = "idle" | "processing" | "conflicts" | "error";

const SUCCESS_COPY = "Passport details added. Please verify them against your passport.";

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

function PassportIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" strokeWidth="1.75" />
      <circle cx="12" cy="10" r="2.5" stroke="currentColor" strokeWidth="1.75" />
      <path d="M8 16.5c1.2-1.4 2.5-2 4-2s2.8.6 4 2" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
    </svg>
  );
}

function progressLabel(status: string, progress: number): string {
  if (status === "preparing") return "Preparing passport image…";
  if (status === "loading_ocr") return "Starting on-device reader…";
  if (status === "recognizing") return `Reading passport on this device… ${Math.round(progress * 100)}%`;
  if (status === "parsing") return "Checking passport details…";
  return "Reading passport on this device…";
}

export function DocumentReader({ passengerIndex, passenger, onApply }: DocumentReaderProps) {
  const fileRef = useRef<HTMLInputElement>(null);
  const abortRef = useRef<AbortController | null>(null);
  const [phase, setPhase] = useState<ReaderPhase>("idle");
  const [result, setResult] = useState<MrzParseResult | null>(null);
  const [conflicts, setConflicts] = useState<FieldConflict[]>([]);
  const [choices, setChoices] = useState<
    Partial<Record<keyof MrzExtractedFields, "keep" | "use_extracted">>
  >({});
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [progressText, setProgressText] = useState("Reading passport on this device…");
  const [uncertainFields, setUncertainFields] = useState<Array<keyof MrzExtractedFields>>([]);

  useEffect(() => {
    return () => {
      abortRef.current?.abort();
    };
  }, []);

  const applyExtraction = (parsed: MrzParseResult, conflictChoices?: Partial<Record<keyof MrzExtractedFields, "keep" | "use_extracted">>) => {
    let next = applyConfirmedExtraction(passenger, parsed.fields, conflictChoices);
    next = applyTitleAssistance(next, {
      gender: parsed.fields.gender,
    });
    onApply(next);
    const uncertain = parsed.confidence
      .filter((row) => row.confidence === "low" || row.confidence === "medium")
      .map((row) => row.field)
      .filter((field): field is keyof MrzExtractedFields => field in FIELD_LABELS);
    setUncertainFields(uncertain);
    setStatusMessage(SUCCESS_COPY);
    setPhase("idle");
    setResult(null);
    setConflicts([]);
    setChoices({});
  };

  const runParse = async (source: Parameters<typeof scanDocumentClientSide>[0]) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setPhase("processing");
    setStatusMessage(null);
    setResult(null);
    setConflicts([]);
    setChoices({});
    setUncertainFields([]);
    setProgressText("Reading passport on this device…");

    try {
      const parsed = await scanDocumentClientSide(source, {
        signal: controller.signal,
        timeoutMs: 45_000,
        onProgress: ({ status, progress }) => {
          setProgressText(progressLabel(status, progress));
        },
      });
      setResult(parsed);

      if (!parsed.ok && Object.keys(parsed.fields).length === 0) {
        setPhase("error");
        setStatusMessage(parsed.warnings[0] ?? "Could not read passport details from that image.");
        return;
      }

      const plan = planExtractedFieldMerge(passenger, parsed.fields);
      if (plan.conflicts.length > 0) {
        // Apply empty-field fills immediately; only ask about conflicts.
        if (Object.keys(plan.toApply).length > 0) {
          let partial = { ...passenger, ...plan.toApply, document_type: "passport" as const };
          partial = applyTitleAssistance(partial, { gender: parsed.fields.gender });
          onApply(partial);
        }
        setConflicts(plan.conflicts);
        setPhase("conflicts");
        setStatusMessage("Some fields already have values. Choose keep or replace — nothing is overwritten silently.");
        return;
      }

      applyExtraction(parsed);
    } catch {
      setPhase("error");
      setStatusMessage("Could not read the passport on this device. Try a clearer photo of the data page.");
    } finally {
      if (abortRef.current === controller) {
        abortRef.current = null;
      }
    }
  };

  const handleFile = (file: File | undefined) => {
    if (!file) return;
    void runParse({ kind: "image", file });
  };

  const handleConfirmConflicts = () => {
    if (!result) return;
    const unresolved = conflicts.filter((conflict) => choices[conflict.field] == null);
    if (unresolved.length > 0) {
      setStatusMessage("Resolve each highlighted field before continuing.");
      return;
    }
    const resolvedChoices: Partial<Record<keyof MrzExtractedFields, "keep" | "use_extracted">> = {
      ...choices,
    };
    for (const conflict of conflicts) {
      resolvedChoices[conflict.field] = choices[conflict.field] ?? "keep";
    }
    applyExtraction(result, resolvedChoices);
  };

  const handleCancel = () => {
    abortRef.current?.abort();
    abortRef.current = null;
    setPhase("idle");
    setResult(null);
    setConflicts([]);
    setChoices({});
    setStatusMessage("Passport scan cancelled.");
  };

  return (
    <div
      className="sm:col-span-2 font-sans"
      data-testid={`document-reader-${passengerIndex}`}
      data-architecture="CLIENT_SIDE"
    >
      {phase === "idle" || phase === "error" ? (
        <div className="flex flex-wrap items-center gap-3">
          <button
            type="button"
            className="inline-flex items-center gap-2 rounded-jp-md border border-jp-border bg-white px-3 py-2 text-sm font-semibold text-jp-text hover:border-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus disabled:opacity-60"
            data-testid={`document-reader-scan-${passengerIndex}`}
            onClick={() => fileRef.current?.click()}
            aria-label="Autofill from passport"
          >
            <PassportIcon className="h-4 w-4 text-jp-primary" />
            Autofill from passport
          </button>
          <p className="text-xs text-jp-muted">Photo stays on this device.</p>
        </div>
      ) : null}

      <input
        ref={fileRef}
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        data-testid={`document-reader-file-${passengerIndex}`}
        onChange={(event) => {
          handleFile(event.target.files?.[0]);
          event.target.value = "";
        }}
      />

      {/* Hidden autofill fixture surface for automated tests; not shown in the customer UI. */}
      <textarea
        className="sr-only"
        aria-hidden="true"
        tabIndex={-1}
        data-testid={`document-reader-paste-${passengerIndex}`}
        onChange={(event) => {
          const text = event.target.value;
          if (text.trim()) void runParse({ kind: "text", text });
        }}
      />

      {phase === "processing" ? (
        <div className="mt-2 space-y-2" data-testid={`document-reader-processing-${passengerIndex}`}>
          <p className="text-sm text-jp-muted" role="status">
            {progressText}
          </p>
          <button
            type="button"
            className="rounded-jp-md border border-jp-border bg-white px-3 py-1.5 text-xs font-semibold text-jp-text"
            data-testid={`document-reader-cancel-${passengerIndex}`}
            onClick={handleCancel}
          >
            Cancel
          </button>
        </div>
      ) : null}

      {phase === "error" && statusMessage ? (
        <div className="mt-2 space-y-2" data-testid={`document-reader-error-${passengerIndex}`}>
          <p className="text-sm text-red-700" role="alert">
            {statusMessage}
          </p>
          <button
            type="button"
            className="rounded-jp-md border border-jp-border bg-white px-3 py-1.5 text-xs font-semibold text-jp-text"
            data-testid={`document-reader-retry-${passengerIndex}`}
            onClick={() => fileRef.current?.click()}
          >
            Retry with a clearer image
          </button>
        </div>
      ) : null}

      {phase === "conflicts" && result ? (
        <div
          className="mt-2 space-y-3 rounded-jp-md border border-jp-border bg-jp-page/60 p-3"
          data-testid={`document-reader-conflicts-${passengerIndex}`}
        >
          <p className="text-xs font-semibold text-jp-text" role="status">
            {statusMessage}
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
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className="rounded-jp-md bg-jp-primary px-3 py-2 text-xs font-semibold text-white focus-visible:outline-none focus-visible:shadow-jp-focus"
              data-testid={`document-reader-confirm-${passengerIndex}`}
              onClick={handleConfirmConflicts}
            >
              Apply choices
            </button>
            <button
              type="button"
              className="rounded-jp-md border border-jp-border bg-white px-3 py-2 text-xs font-semibold text-jp-text"
              data-testid={`document-reader-discard-${passengerIndex}`}
              onClick={handleCancel}
            >
              Discard
            </button>
          </div>
        </div>
      ) : null}

      {phase === "idle" && statusMessage ? (
        <p className="mt-2 text-xs text-jp-muted" role="status" data-testid={`document-reader-status-${passengerIndex}`}>
          {statusMessage}
        </p>
      ) : null}

      {uncertainFields.length > 0 ? (
        <p
          className="mt-2 rounded-jp-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
          role="status"
          data-testid={`document-reader-uncertain-${passengerIndex}`}
        >
          Please double-check: {uncertainFields.map((field) => FIELD_LABELS[field]).join(", ")}.
        </p>
      ) : null}
    </div>
  );
}
