import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BookingPageHeaderProps = {
  title: string;
  description?: string;
  actions?: ReactNode;
  className?: string;
};

export function BookingPageHeader({ title, description, actions, className }: BookingPageHeaderProps) {
  return (
    <header className={cn("flex flex-wrap items-start justify-between gap-3", className)}>
      <div className="min-w-0">
        <h1 className="text-jp-xl font-semibold text-jp-text sm:text-2xl">{title}</h1>
        {description ? <p className="mt-1 text-jp-sm text-jp-muted">{description}</p> : null}
      </div>
      {actions ? <div className="shrink-0">{actions}</div> : null}
    </header>
  );
}
