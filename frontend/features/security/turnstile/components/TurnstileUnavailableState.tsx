import Link from "next/link";

type TurnstileUnavailableStateProps = {
  bladeFallbackHref: string;
};

export function TurnstileUnavailableState({ bladeFallbackHref }: TurnstileUnavailableStateProps) {
  return (
    <div
      className="rounded-jp-md border border-amber-200 bg-amber-50 p-3 text-jp-sm text-amber-900"
      role="alert"
      data-testid="turnstile-unavailable"
    >
      <p className="font-medium">Security check unavailable</p>
      <p className="mt-1">
        We could not load the security verification widget. You can continue using the secure booking lookup form instead.
      </p>
      <Link href={bladeFallbackHref} className="mt-2 inline-block font-semibold underline focus-visible:shadow-jp-focus">
        Use secure booking lookup
      </Link>
    </div>
  );
}
