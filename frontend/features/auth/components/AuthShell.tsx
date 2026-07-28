import { PageContainer } from "@/components/layout/PageContainer";
import type { ReactNode } from "react";

type AuthShellProps = {
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
};

export function AuthShell({ title, description, children, footer }: AuthShellProps) {
  return (
    <PageContainer className="py-10 sm:py-14">
      <div className="mx-auto w-full max-w-lg">
        <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-6 shadow-jp-sm sm:p-8">
          <header className="mb-6 space-y-2">
            <h1 className="text-jp-h2 font-bold text-jp-text">{title}</h1>
            {description ? <p className="text-jp-sm text-jp-muted">{description}</p> : null}
          </header>
          {children}
        </div>
        {footer ? <div className="mt-6 text-center text-jp-sm text-jp-muted">{footer}</div> : null}
      </div>
    </PageContainer>
  );
}
