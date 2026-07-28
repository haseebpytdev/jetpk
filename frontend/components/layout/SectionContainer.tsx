import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type SectionContainerProps = {
  children: ReactNode;
  className?: string;
  as?: "section" | "div";
  id?: string;
  ariaLabelledby?: string;
};

export function SectionContainer({
  children,
  className,
  as: Component = "section",
  id,
  ariaLabelledby,
}: SectionContainerProps) {
  return (
    <Component
      id={id}
      aria-labelledby={ariaLabelledby}
      className={cn("py-jp-3xl", className)}
    >
      {children}
    </Component>
  );
}
