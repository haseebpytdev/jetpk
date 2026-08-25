"use client";

import { Dialog } from "@/components/ui/Dialog";
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
  const difference =
    originalTotal != null && confirmedTotal != null ? confirmedTotal - originalTotal : null;

  return (
    <Dialog
      open={open}
      onClose={onCancel}
      title="Fare updated"
      description="The airline updated this fare during verification. Review the new total before continuing."
      footer={
        <>
          <SecondaryButton type="button" disabled={loading} onClick={onCancel}>
            Choose another flight
          </SecondaryButton>
          <PrimaryButton type="button" disabled={loading} onClick={onAccept}>
            {loading ? "Continuing…" : "Accept new fare"}
          </PrimaryButton>
        </>
      }
    >
      <dl className="space-y-2 text-sm" data-testid="fare-change-dialog">
        <div className="flex justify-between gap-2">
          <dt className="text-jp-text-muted">Previous total</dt>
          <dd>{formatAmount(originalTotal, currency)}</dd>
        </div>
        <div className="flex justify-between gap-2 font-semibold">
          <dt>Current total</dt>
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
    </Dialog>
  );
}
