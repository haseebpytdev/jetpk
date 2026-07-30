import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BookingSectionProps = {
  children: ReactNode;
  className?: string;
  testId?: string;
};

export function BookingSection({ children, className, testId }: BookingSectionProps) {
  return (
    <section
      className={cn("rounded-jp-lg border border-jp-border bg-jp-surface p-4 sm:p-5", className)}
      data-testid={testId}
    >
      {children}
    </section>
  );
}
