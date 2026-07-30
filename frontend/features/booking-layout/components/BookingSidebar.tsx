import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BookingSidebarProps = {
  children: ReactNode;
  className?: string;
  sticky?: boolean;
  label?: string;
};

export function BookingSidebar({
  children,
  className,
  sticky = true,
  label = "Order summary",
}: BookingSidebarProps) {
  return (
    <aside
      aria-label={label}
      className={cn(
        "space-y-4",
        sticky && "lg:sticky lg:top-4 lg:self-start",
        className,
      )}
    >
      {children}
    </aside>
  );
}
