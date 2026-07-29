import { SecondaryButton } from "@/components/ui/SecondaryButton";

type OfferStatePanelProps = {
  title: string;
  message: string;
  onClose?: () => void;
  onRetry?: () => void;
  onNewSearch?: () => void;
};

export function OfferUnavailableState({ title, message, onClose, onNewSearch }: OfferStatePanelProps) {
  return (
    <div className="rounded-jp-md border border-amber-200 bg-amber-50 p-4" data-testid="offer-unavailable-state" role="alert">
      <h3 className="font-semibold text-amber-900">{title}</h3>
      <p className="mt-1 text-sm text-amber-800">{message}</p>
      <div className="mt-3 flex flex-wrap gap-2">
        {onClose ? (
          <SecondaryButton type="button" onClick={onClose}>
            Back to results
          </SecondaryButton>
        ) : null}
        {onNewSearch ? (
          <SecondaryButton type="button" onClick={onNewSearch}>
            New search
          </SecondaryButton>
        ) : null}
      </div>
    </div>
  );
}

export function OfferExpiredState({ message, onClose, onNewSearch }: Omit<OfferStatePanelProps, "title">) {
  return (
    <div className="rounded-jp-md border border-jp-border bg-jp-muted/40 p-4" data-testid="offer-expired-state" role="alert">
      <h3 className="font-semibold text-jp-text">Search expired</h3>
      <p className="mt-1 text-sm text-jp-text-muted">{message}</p>
      <div className="mt-3 flex flex-wrap gap-2">
        {onClose ? (
          <SecondaryButton type="button" onClick={onClose}>
            Back to results
          </SecondaryButton>
        ) : null}
        {onNewSearch ? (
          <SecondaryButton type="button" onClick={onNewSearch}>
            New search
          </SecondaryButton>
        ) : null}
      </div>
    </div>
  );
}

export function SupplierTimeoutState({ message, onRetry, onClose }: Omit<OfferStatePanelProps, "title">) {
  return (
    <div className="rounded-jp-md border border-jp-border bg-jp-muted/40 p-4" data-testid="supplier-timeout-state" role="alert">
      <h3 className="font-semibold text-jp-text">Request timed out</h3>
      <p className="mt-1 text-sm text-jp-text-muted">{message}</p>
      <div className="mt-3 flex flex-wrap gap-2">
        {onRetry ? (
          <SecondaryButton type="button" onClick={onRetry}>
            Try again
          </SecondaryButton>
        ) : null}
        {onClose ? (
          <SecondaryButton type="button" onClick={onClose}>
            Back to results
          </SecondaryButton>
        ) : null}
      </div>
    </div>
  );
}

export function RevalidationPanel({ message }: { message: string }) {
  return (
    <p className="text-sm text-jp-text-muted" role="status" aria-live="polite" data-testid="revalidation-status">
      {message}
    </p>
  );
}
