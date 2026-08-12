import Link from "next/link";

type TurnstileUnavailableStateProps = {
  /** Modern recovery path (support / retry target). Never a legacy Blade handoff. */
  recoveryHref?: string;
  title?: string;
  body?: string;
  linkLabel?: string;
  onRetry?: () => void;
};

export function TurnstileUnavailableState({
  recoveryHref = "/support",
  title = "Security check unavailable",
  body = "We could not load the security verification widget. Refresh this page to try again, or contact support if the problem continues.",
  linkLabel = "Contact support",
  onRetry,
}: TurnstileUnavailableStateProps) {
  return (
    <div
      className="rounded-jp-md border border-amber-200 bg-amber-50 p-3 text-jp-sm text-amber-900"
      role="alert"
      data-testid="turnstile-unavailable"
    >
      <p className="font-medium">{title}</p>
      <p className="mt-1">{body}</p>
      <div className="mt-2 flex flex-wrap gap-3">
        {onRetry ? (
          <button
            type="button"
            className="font-semibold underline focus-visible:shadow-jp-focus"
            onClick={onRetry}
            data-testid="turnstile-unavailable-retry"
          >
            Try again
          </button>
        ) : null}
        <Link
          href={recoveryHref}
          className="inline-block font-semibold underline focus-visible:shadow-jp-focus"
          data-testid="turnstile-unavailable-recovery"
        >
          {linkLabel}
        </Link>
      </div>
    </div>
  );
}
