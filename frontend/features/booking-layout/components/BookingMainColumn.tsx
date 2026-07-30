import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BookingMainColumnProps = {
  children: ReactNode;
  className?: string;
};

export function BookingMainColumn({ children, className }: BookingMainColumnProps) {
  return <div className={cn("min-w-0 space-y-4", className)}>{children}</div>;
}
