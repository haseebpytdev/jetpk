import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type EmptyStateProps = {
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
  testId?: string;
};

export function EmptyState({ title, description, action, className, testId = "empty-state" }: EmptyStateProps) {
  return (
    <div
      className={cn(
        "rounded-jp-lg border border-jp-border bg-jp-surface px-jp-lg py-jp-xl text-center",
        className,
      )}
      data-testid={testId}
    >
      <div
        className="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-jp-brand-soft text-jp-brand"
        aria-hidden="true"
      >
        <EmptyIcon />
      </div>
      <h2 className="font-sans text-jp-md font-semibold text-jp-text">{title}</h2>
      {description ? <p className="mx-auto mt-2 max-w-md text-jp-sm text-jp-muted">{description}</p> : null}
      {action ? <div className="mt-jp-md">{action}</div> : null}
    </div>
  );
}

function EmptyIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" aria-hidden="true">
      <rect x="4" y="6" width="16" height="12" rx="2" stroke="currentColor" strokeWidth="1.8" />
      <path d="M8 10h8M8 14h5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </svg>
  );
}
