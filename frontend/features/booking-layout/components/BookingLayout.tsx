import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BookingLayoutProps = {
  main: ReactNode;
  sidebar?: ReactNode;
  mobileSummary?: ReactNode;
  className?: string;
};

export function BookingLayout({ main, sidebar, mobileSummary, className }: BookingLayoutProps) {
  return (
    <div className={cn("jp-booking-shell mt-6", className)}>
      {mobileSummary ? <div className="mb-4 lg:hidden">{mobileSummary}</div> : null}
      <div className="jp-booking-grid grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,21rem)]">
        {main}
        {sidebar ? <div className="hidden lg:block">{sidebar}</div> : null}
      </div>
    </div>
  );
}
