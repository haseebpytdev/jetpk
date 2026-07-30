import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BookingSectionHeaderProps = {
  title: string;
  description?: string;
  actions?: ReactNode;
  className?: string;
};

export function BookingSectionHeader({ title, description, actions, className }: BookingSectionHeaderProps) {
  return (
    <div className={cn("mb-4 flex flex-wrap items-start justify-between gap-2", className)}>
      <div>
        <h2 className="text-jp-base font-semibold text-jp-text">{title}</h2>
        {description ? <p className="mt-0.5 text-jp-sm text-jp-muted">{description}</p> : null}
      </div>
      {actions ? <div className="shrink-0">{actions}</div> : null}
    </div>
  );
}
