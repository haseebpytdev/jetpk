import type { ButtonHTMLAttributes, ReactNode } from "react";

type PublicIconButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  label: string;
  children: ReactNode;
};

export function PublicIconButton({ label, children, className, type = "button", ...props }: PublicIconButtonProps) {
  return (
    <button
      type={type}
      aria-label={label}
      className={["jp-v2-icon-btn", className].filter(Boolean).join(" ")}
      {...props}
    >
      {children}
    </button>
  );
}
