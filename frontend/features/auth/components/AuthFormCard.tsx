import type { ReactNode } from "react";

type AuthFormCardProps = {
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
  secondaryCard?: ReactNode;
};

export function AuthFormCard({ title, description, children, footer, secondaryCard }: AuthFormCardProps) {
  return (
    <div className="space-y-4" data-testid="auth-form-card">
      <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-sm sm:p-jp-xl">
        <header className="mb-jp-md space-y-2">
          <h1 className="font-display text-jp-h3 font-bold text-jp-text">{title}</h1>
          {description ? <p className="text-jp-sm text-jp-muted">{description}</p> : null}
        </header>
        {children}
        {footer ? <div className="mt-jp-md border-t border-jp-border pt-jp-md text-center text-jp-sm text-jp-muted">{footer}</div> : null}
      </div>
      {secondaryCard ? (
        <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-sm">{secondaryCard}</div>
      ) : null}
    </div>
  );
}
