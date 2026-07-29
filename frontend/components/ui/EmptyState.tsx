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
        "rounded-jp-lg border border-jp-border bg-jp-surface p-jp-xl text-center",
        className,
      )}
      data-testid={testId}
    >
      <h2 className="text-jp-md font-semibold text-jp-text">{title}</h2>
      {description ? <p className="mt-2 text-jp-sm text-jp-muted">{description}</p> : null}
      {action ? <div className="mt-jp-lg">{action}</div> : null}
    </div>
  );
}
