"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";

export type GroupCheckoutDecisionModal = {
  title: string;
  body: string;
  primary_action: string | null;
  secondary_action: string;
};

type GroupCheckoutDecisionDialogProps = {
  open: boolean;
  modal: GroupCheckoutDecisionModal | null;
  onPrimary?: () => void;
  onSecondary: () => void;
  primaryDisabled?: boolean;
};

/**
 * Accessible floating dialog for final availability / fare changes before payment.
 */
export function GroupCheckoutDecisionDialog({
  open,
  modal,
  onPrimary,
  onSecondary,
  primaryDisabled = false,
}: GroupCheckoutDecisionDialogProps) {
  if (!open || !modal) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
      role="presentation"
      data-testid="group-checkout-decision-dialog"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="group-checkout-decision-title"
        aria-describedby="group-checkout-decision-body"
        className="w-full max-w-md rounded-jp-lg border border-jp-border bg-jp-surface p-5 shadow-lg"
      >
        <h2 id="group-checkout-decision-title" className="text-lg font-semibold text-jp-text">
          {modal.title}
        </h2>
        <p id="group-checkout-decision-body" className="mt-2 text-jp-sm text-jp-muted">
          {modal.body}
        </p>
        <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
          <button
            type="button"
            className="rounded-jp-md border border-jp-border px-4 py-2 text-jp-sm text-jp-text"
            onClick={onSecondary}
            data-testid="group-checkout-decision-secondary"
          >
            {modal.secondary_action}
          </button>
          {modal.primary_action && onPrimary ? (
            <PrimaryButton
              type="button"
              onClick={onPrimary}
              disabled={primaryDisabled}
              data-testid="group-checkout-decision-primary"
            >
              {modal.primary_action}
            </PrimaryButton>
          ) : null}
        </div>
      </div>
    </div>
  );
}
