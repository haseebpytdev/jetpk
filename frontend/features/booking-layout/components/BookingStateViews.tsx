import type { ReactNode } from "react";

type BookingLoadingStateProps = {
  message?: string;
  testId?: string;
};

export function BookingLoadingState({
  message = "Loading…",
  testId = "booking-loading-state",
}: BookingLoadingStateProps) {
  return (
    <div className="mx-auto max-w-jp-booking px-4 py-12" data-testid={testId}>
      <div className="flex items-center gap-3 text-jp-sm text-jp-muted" role="status" aria-live="polite">
        <span
          className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-jp-border border-t-jp-primary"
          aria-hidden="true"
        />
        {message}
      </div>
    </div>
  );
}

type BookingErrorBoundaryFallbackProps = {
  title?: string;
  message?: string;
  children?: ReactNode;
};

export function BookingErrorBoundaryFallback({
  title = "Something went wrong",
  message = "Please refresh the page or return to search.",
  children,
}: BookingErrorBoundaryFallbackProps) {
  return (
    <div className="mx-auto max-w-jp-booking px-4 py-12" role="alert" data-testid="booking-error-boundary">
      <h1 className="text-jp-lg font-semibold text-jp-text">{title}</h1>
      <p className="mt-2 text-jp-sm text-jp-muted">{message}</p>
      {children ? <div className="mt-4">{children}</div> : null}
    </div>
  );
}
