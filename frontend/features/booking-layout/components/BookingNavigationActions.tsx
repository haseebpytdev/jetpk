import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BookingNavigationActionsProps = {
  back?: ReactNode;
  primary: ReactNode;
  className?: string;
};

export function BookingNavigationActions({ back, primary, className }: BookingNavigationActionsProps) {
  return (
    <div
      className={cn(
        "flex flex-col-reverse gap-3 border-t border-jp-border pt-4 sm:flex-row sm:items-center sm:justify-between",
        className,
      )}
      data-testid="booking-navigation-actions"
    >
      {back ? <div className="text-jp-sm">{back}</div> : <span />}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">{primary}</div>
    </div>
  );
}
