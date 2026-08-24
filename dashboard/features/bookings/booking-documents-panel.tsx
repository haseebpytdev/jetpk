"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { generateBookingDocument, generateBookingPaymentReceipt } from "@/services/operational-api";
import type { BookingDocumentEntry, BookingOperationalCapabilities } from "@/types/booking";

const DOCUMENT_ACTIONS: Array<{
  kind: "confirmation" | "invoice" | "ticket-itinerary" | "refund-note" | "cancellation-confirmation";
  label: string;
}> = [
  { kind: "confirmation", label: "Confirmation" },
  { kind: "invoice", label: "Invoice" },
  { kind: "ticket-itinerary", label: "Ticket itinerary" },
  { kind: "refund-note", label: "Refund note" },
  { kind: "cancellation-confirmation", label: "Cancellation confirmation" },
];

export function BookingDocumentsPanel({
  bookingId,
  documents,
  capabilities,
}: {
  bookingId: string;
  documents: BookingDocumentEntry[];
  capabilities?: BookingOperationalCapabilities | null;
}) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const router = useRouter();
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const canGenerate = Boolean(capabilities?.can_generate_documents);
  const canDownload = Boolean(capabilities?.can_download_documents);
  const canReceipt = Boolean(capabilities?.can_generate_receipt);
  const latestPaymentId = capabilities?.latest_payment_id ?? null;

  async function runGenerate(kind: (typeof DOCUMENT_ACTIONS)[number]["kind"]) {
    if (!canGenerate || busy) return;
    setBusy(kind);
    setError(null);
    setSuccess(null);
    const result = await generateBookingDocument(portal, bookingId, kind);
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Document generation failed.");
      return;
    }
    setSuccess(`${kind.replace(/-/g, " ")} generated.`);
    router.refresh();
  }

  async function runReceipt() {
    if (!canReceipt || !latestPaymentId || busy) return;
    setBusy("receipt");
    setError(null);
    setSuccess(null);
    const result = await generateBookingPaymentReceipt(portal, latestPaymentId);
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Receipt generation failed.");
      return;
    }
    setSuccess("Payment receipt generated.");
    router.refresh();
  }

  return (
    <div className="space-y-3" data-testid="booking-documents-panel">
      {documents.length > 0 ? (
        <ul className="space-y-2 text-sm" data-testid="booking-documents">
          {documents.map((document) => (
            <li key={document.documentId} className="flex justify-between gap-3">
              <span>{document.title}</span>
              <span className="flex items-center gap-2 text-xs text-jp-muted capitalize">
                {document.documentType.replace(/_/g, " ")} · {document.status}
                {canDownload && document.downloadUrl ? (
                  <a
                    href={document.downloadUrl}
                    className="font-medium text-jp-accent underline-offset-2 hover:underline"
                    data-testid={`booking-document-download-${document.documentId}`}
                  >
                    Download
                  </a>
                ) : null}
              </span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-sm text-jp-muted" data-testid="booking-documents">
          No document metadata attached yet.
        </p>
      )}

      {!isLive ? (
        <p className="text-xs text-jp-muted">Document generation is available in live dashboard mode only.</p>
      ) : canGenerate ? (
        <div className="flex flex-wrap gap-2" data-testid="booking-document-actions">
          {DOCUMENT_ACTIONS.map((action) => (
            <button
              key={action.kind}
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
              disabled={Boolean(busy)}
              onClick={() => void runGenerate(action.kind)}
              data-testid={`booking-document-generate-${action.kind}`}
            >
              {busy === action.kind ? "Generating…" : action.label}
            </button>
          ))}
          {canReceipt ? (
            <button
              type="button"
              className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
              disabled={Boolean(busy)}
              onClick={() => void runReceipt()}
              data-testid="booking-document-generate-receipt"
            >
              {busy === "receipt" ? "Generating…" : "Payment receipt"}
            </button>
          ) : null}
        </div>
      ) : (
        <p className="text-xs text-jp-muted">Document generation is not permitted for this booking.</p>
      )}

      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
    </div>
  );
}
