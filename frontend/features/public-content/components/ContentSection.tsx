import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type ContentSectionProps = {
  title?: string;
  id?: string;
  className?: string;
  children: ReactNode;
};

export function ContentSection({ title, id, className, children }: ContentSectionProps) {
  return (
    <section id={id} className={cn("space-y-4", className)} aria-labelledby={title ? `${id}-heading` : undefined}>
      {title ? (
        <h2 id={`${id}-heading`} className="font-display text-jp-h3 font-semibold text-jp-text">
          {title}
        </h2>
      ) : null}
      {children}
    </section>
  );
}
