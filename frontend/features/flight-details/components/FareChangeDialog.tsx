"use client";

import { useEffect } from "react";
import { Dialog } from "@/components/ui/Dialog";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { markBookNowTiming } from "@/features/flight-results/utils/book-now-timing";

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

  useEffect(() => {
    if (!open) return;
    markBookNowTiming("T3D_fare_modal_visible", { fare_changed: true });
  }, [open]);

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
          <PrimaryButton
            type="button"
            disabled={loading}
            onClick={() => {
              markBookNowTiming("T3E_fare_accept_clicked", { fare_changed: true });
              onAccept();
            }}
          >
            {loading ? "Continuing…" : "Continue with updated fare"}
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
