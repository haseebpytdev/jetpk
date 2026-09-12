import { cn } from "@/lib/cn";
import { UI_ICON_ACTION_CLASS, UI_ICON_STROKE } from "@/lib/ui-icon";
import { Inbox } from "lucide-react";
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
        <Inbox className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
      </div>
      <h2 className="font-sans text-jp-md font-semibold text-jp-text">{title}</h2>
      {description ? <p className="mx-auto mt-2 max-w-md text-jp-sm text-jp-muted">{description}</p> : null}
      {action ? <div className="mt-jp-md">{action}</div> : null}
    </div>
  );
}
