import Link from "next/link";

type TurnstileUnavailableStateProps = {
  bladeFallbackHref: string;
  title?: string;
  body?: string;
  linkLabel?: string;
};

export function TurnstileUnavailableState({
  bladeFallbackHref,
  title = "Security check unavailable",
  body = "We could not load the security verification widget. You can continue using the secure booking lookup form instead.",
  linkLabel = "Use secure booking lookup",
}: TurnstileUnavailableStateProps) {
  return (
    <div
      className="rounded-jp-md border border-amber-200 bg-amber-50 p-3 text-jp-sm text-amber-900"
      role="alert"
      data-testid="turnstile-unavailable"
    >
      <p className="font-medium">{title}</p>
      <p className="mt-1">{body}</p>
      <Link href={bladeFallbackHref} className="mt-2 inline-block font-semibold underline focus-visible:shadow-jp-focus">
        {linkLabel}
      </Link>
    </div>
  );
}
