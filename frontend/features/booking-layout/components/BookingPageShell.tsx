import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BookingPageShellProps = {
  children: ReactNode;
  className?: string;
  testId?: string;
};

export function BookingPageShell({ children, className, testId }: BookingPageShellProps) {
  return (
    <div
      className={cn("mx-auto w-full max-w-jp-booking px-4 py-6 sm:px-6 lg:px-8", className)}
      data-testid={testId}
    >
      {children}
    </div>
  );
}
