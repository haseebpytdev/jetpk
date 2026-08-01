import type { ReactNode } from "react";

type PublicEmptyStateProps = {
  title: string;
  description?: string;
  action?: ReactNode;
};

export function PublicEmptyState({ title, description, action }: PublicEmptyStateProps) {
  return (
    <div className="jp-v2-empty" role="status">
      <p className="jp-v2-empty__title">{title}</p>
      {description ? <p style={{ color: "var(--jp-v2-text-muted)", margin: "0 0 var(--jp-v2-space-lg)" }}>{description}</p> : null}
      {action}
    </div>
  );
}
