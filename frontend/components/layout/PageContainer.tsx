import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type PageContainerProps = {
  children: ReactNode;
  className?: string;
  /** Narrow form layouts */
  narrow?: boolean;
  /** Booking/checkout width */
  booking?: boolean;
  /** Full-bleed (no max-width) */
  fullBleed?: boolean;
};

export function PageContainer({ children, className, narrow, booking, fullBleed }: PageContainerProps) {
  return (
    <div
      className={cn(
        "mx-auto w-full min-w-0 max-w-full px-jp-xl",
        !fullBleed && (narrow ? "max-w-jp-narrow" : booking ? "max-w-jp-booking" : "max-w-jp-container"),
        className,
      )}
    >
      {children}
    </div>
  );
}
