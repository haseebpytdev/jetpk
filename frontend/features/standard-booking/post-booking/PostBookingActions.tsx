import Link from "next/link";
import type { PostBookingAction } from "../types/review-payment";
import { isAllowedBookingNextUrl } from "../utils/allowlist";

type PostBookingActionsProps = {
  actions: PostBookingAction[];
};

export function PostBookingActions({ actions }: PostBookingActionsProps) {
  const available = actions.filter((action) => action.available && action.url && isAllowedBookingNextUrl(action.url));

  return (
    <nav className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 print:hidden" aria-label="Post-booking actions" data-testid="post-booking-actions">
      <h2 className="text-jp-base font-semibold text-jp-text">Next steps</h2>
      <ul className="mt-3 flex flex-wrap gap-2">
        {available.map((action) => (
          <li key={action.code}>
            <Link
              href={action.url!}
              className="inline-flex min-h-10 items-center rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold text-jp-text focus-visible:shadow-jp-focus"
              data-testid={`action-${action.code}`}
            >
              {action.label}
            </Link>
          </li>
        ))}
      </ul>
      {actions
        .filter((action) => !action.available && action.reason_unavailable)
        .map((action) => (
          <p key={action.code} className="mt-2 text-jp-xs text-jp-muted" role="note">
            {action.reason_unavailable}
          </p>
        ))}
    </nav>
  );
}
