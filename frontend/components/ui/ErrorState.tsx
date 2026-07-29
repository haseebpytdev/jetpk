import { cn } from "@/lib/cn";
import type { ReactNode } from "react";
import { Button } from "./Button";

type ErrorStateProps = {
  title?: string;
  message: string;
  onRetry?: () => void;
  retryLabel?: string;
  supportLink?: ReactNode;
  className?: string;
  testId?: string;
};

export function ErrorState({
  title = "Something went wrong",
  message,
  onRetry,
  retryLabel = "Try again",
  supportLink,
  className,
  testId = "error-state",
}: ErrorStateProps) {
  return (
    <div
      className={cn(
        "rounded-jp-lg border border-jp-danger bg-jp-danger-soft p-jp-xl text-center",
        className,
      )}
      role="alert"
      data-testid={testId}
    >
      <h2 className="text-jp-md font-semibold text-jp-danger">{title}</h2>
      <p className="mt-2 text-jp-sm text-jp-text">{message}</p>
      {onRetry ? (
        <div className="mt-jp-lg">
          <RetryState onRetry={onRetry} label={retryLabel} />
        </div>
      ) : null}
      {supportLink ? <div className="mt-jp-md text-jp-sm">{supportLink}</div> : null}
    </div>
  );
}

type RetryStateProps = {
  onRetry: () => void;
  label?: string;
  busy?: boolean;
  className?: string;
};

export function RetryState({ onRetry, label = "Try again", busy = false, className }: RetryStateProps) {
  return (
    <Button variant="secondary" onClick={onRetry} busy={busy} disabled={busy} className={className}>
      {label}
    </Button>
  );
}
