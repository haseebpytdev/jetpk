import type { ReactNode } from "react";

type AuthFormPanelProps = {
  children: ReactNode;
};

export function AuthFormPanel({ children }: AuthFormPanelProps) {
  return (
    <div className="flex w-full flex-col justify-center lg:max-w-xl lg:justify-self-end" data-testid="auth-form-panel">
      {children}
    </div>
  );
}
