"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";

type FareChangeDialogProps = {
  open: boolean;
  originalTotal?: number;
  confirmedTotal?: number;
  currency?: string;
  loading?: boolean;
  onAccept: () => void;
  onCancel: () => void;
};

function formatAmount(value: number | undefined, currency: string): string {
  if (value == null) return "—";
  return `${Math.round(value).toLocaleString("en-PK")} ${currency}`;
}

export function FareChangeDialog({
  open,
  originalTotal,
  confirmedTotal,
  currency = "PKR",
  loading,
  onAccept,
  onCancel,
}: FareChangeDialogProps) {
  if (!open) return null;

  const difference =
    originalTotal != null && confirmedTotal != null ? confirmedTotal - originalTotal : null;

  return (
    <div
      className="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="fare-change-title"
      data-testid="fare-change-dialog"
    >
      <div className="w-full max-w-md rounded-jp-lg bg-jp-surface p-4 shadow-jp-card">
        <h2 id="fare-change-title" className="text-lg font-semibold text-jp-text">
          Fare has changed
        </h2>
        <p className="mt-2 text-sm text-jp-text-muted">
          The airline updated the price. Review the new total before continuing.
        </p>
        <dl className="mt-4 space-y-2 text-sm">
          <div className="flex justify-between gap-2">
            <dt className="text-jp-text-muted">Previous price</dt>
            <dd>{formatAmount(originalTotal, currency)}</dd>
          </div>
          <div className="flex justify-between gap-2 font-semibold">
            <dt>New price</dt>
            <dd>{formatAmount(confirmedTotal, currency)}</dd>
          </div>
          {difference != null ? (
            <div className="flex justify-between gap-2 text-jp-text-muted">
              <dt>Difference</dt>
              <dd>
                {difference >= 0 ? "+" : ""}
                {formatAmount(difference, currency)}
              </dd>
            </div>
          ) : null}
        </dl>
        <div className="mt-4 flex flex-col gap-2 sm:flex-row">
          <PrimaryButton type="button" className="flex-1" disabled={loading} onClick={onAccept}>
            {loading ? "Continuing…" : "Accept new fare"}
          </PrimaryButton>
          <SecondaryButton type="button" className="flex-1" disabled={loading} onClick={onCancel}>
            Go back
          </SecondaryButton>
        </div>
      </div>
    </div>
  );
}
