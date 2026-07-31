import { cn } from "@/lib/cn";
import type { ComponentPropsWithoutRef, ReactNode } from "react";

type PageContainerProps = ComponentPropsWithoutRef<"div"> & {
  children: ReactNode;
  /** Narrow form layouts */
  narrow?: boolean;
  /** Booking/checkout width */
  booking?: boolean;
  /** Full-bleed (no max-width) */
  fullBleed?: boolean;
};

export function PageContainer({
  children,
  className,
  narrow,
  booking,
  fullBleed,
  ...rest
}: PageContainerProps) {
  return (
    <div
      {...rest}
      className={cn(
        "mx-auto w-full px-jp-xl",
        !fullBleed && (narrow ? "max-w-jp-narrow" : booking ? "max-w-jp-booking" : "max-w-jp-container"),
        className,
      )}
    >
      {children}
    </div>
  );
}
