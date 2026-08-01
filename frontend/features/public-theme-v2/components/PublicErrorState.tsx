import type { ReactNode } from "react";

type PublicErrorStateProps = {
  title: string;
  description?: string;
  action?: ReactNode;
};

export function PublicErrorState({ title, description, action }: PublicErrorStateProps) {
  return (
    <div className="jp-v2-error" role="alert">
      <p className="jp-v2-error__title">{title}</p>
      {description ? <p style={{ color: "var(--jp-v2-text-muted)", margin: "0 0 var(--jp-v2-space-lg)" }}>{description}</p> : null}
      {action}
    </div>
  );
}
