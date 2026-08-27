import Link from "next/link";
import { PrimaryButton } from "@/components/ui/PrimaryButton";

type GroupStateProps = {
  title: string;
  message: string;
  actionLabel?: string;
  actionHref?: string;
  testId?: string;
};

function GroupStateCard({ title, message, actionLabel, actionHref, testId }: GroupStateProps) {
  return (
    <section className="mx-auto max-w-xl rounded-jp-lg border border-jp-border bg-jp-surface p-6 text-center" data-testid={testId}>
      <h1 className="text-xl font-semibold text-jp-text">{title}</h1>
      <p className="mt-2 text-jp-sm text-jp-muted">{message}</p>
      {actionLabel && actionHref ? (
        <div className="mt-4">
          <Link href={actionHref}>
            <PrimaryButton>{actionLabel}</PrimaryButton>
          </Link>
        </div>
      ) : null}
    </section>
  );
}

export function GroupUnavailableState() {
  return (
    <GroupStateCard
      testId="group-unavailable-state"
      title="Package unavailable"
      message="This group package is no longer available. Please search for another departure."
      actionLabel="Back to search"
      actionHref="/groups/search"
    />
  );
}

export function GroupHoldExpiredState() {
  return (
    <GroupStateCard
      testId="group-hold-expired-state"
      title="Reservation expired"
      message="Your 25-minute hold has expired and the seats have been released. Please search again to continue."
      actionLabel="Search group fares"
      actionHref="/groups/search"
    />
  );
}

export function GroupLockedState({ message }: { message?: string }) {
  return (
    <GroupStateCard
      testId="group-locked-state"
      title="Booking access restricted"
      message={message ?? "Your group booking access is temporarily restricted. Please contact support."}
      actionLabel="Contact support"
      actionHref="/support"
    />
  );
}

export function GroupBookingErrorState({ title, message }: { title: string; message: string }) {
  return (
    <GroupStateCard
      testId="group-booking-error-state"
      title={title}
      message={message}
      actionLabel="Back to search"
      actionHref="/groups/search"
    />
  );
}

export function GroupEmptyResultsState() {
  return (
    <GroupStateCard
      testId="group-empty-results-state"
      title="No group departures found"
      message="Try another airline, sector, travel date, or category."
      actionLabel="Modify search"
      actionHref="/groups/search"
    />
  );
}
