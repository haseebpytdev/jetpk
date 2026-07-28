import Link from "next/link";
import { PrimaryButton } from "@/components/ui/PrimaryButton";

type PublicContentErrorStateProps = {
  title?: string;
  message?: string;
  onRetry?: () => void;
};

export function PublicContentErrorState({
  title = "Something went wrong",
  message = "We could not load this page. Please try again.",
  onRetry,
}: PublicContentErrorStateProps) {
  return (
    <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-2xl text-center shadow-jp-card" role="alert">
      <h2 className="text-jp-h3 font-semibold text-jp-text">{title}</h2>
      <p className="mt-3 text-jp-body text-jp-muted">{message}</p>
      <div className="mt-6 flex flex-wrap justify-center gap-3">
        {onRetry ? (
          <PrimaryButton type="button" onClick={onRetry}>
            Try again
          </PrimaryButton>
        ) : null}
        <Link
          href="/"
          className="inline-flex min-h-jp-button items-center rounded-jp-md border border-jp-border px-4 text-jp-sm font-medium text-jp-text hover:bg-jp-page focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          Home
        </Link>
        <Link
          href="/support"
          className="inline-flex min-h-jp-button items-center rounded-jp-md border border-jp-border px-4 text-jp-sm font-medium text-jp-text hover:bg-jp-page focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          Support
        </Link>
      </div>
    </div>
  );
}
