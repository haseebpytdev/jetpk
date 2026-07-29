import { cn } from "@/lib/cn";
import type { ButtonHTMLAttributes, ReactNode } from "react";

const variantClasses = {
  primary:
    "bg-jp-brand text-white shadow-jp-sm hover:bg-jp-brand-hover active:bg-jp-brand-active",
  secondary:
    "border border-jp-border bg-jp-surface text-jp-text hover:bg-jp-surface-muted active:bg-jp-surface-muted",
  ghost: "bg-transparent text-jp-text hover:bg-jp-surface-muted",
  danger: "bg-jp-danger text-white hover:opacity-90",
} as const;

type ButtonVariant = keyof typeof variantClasses;

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode;
  variant?: ButtonVariant;
  fullWidth?: boolean;
  busy?: boolean;
};

export function Button({
  children,
  className,
  variant = "primary",
  fullWidth = false,
  busy = false,
  disabled,
  type = "button",
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || busy}
      aria-busy={busy || undefined}
      className={cn(
        "inline-flex min-h-jp-button items-center justify-center gap-2 rounded-jp-button px-jp-lg text-jp-sm font-semibold",
        "transition-colors duration-jp-fast motion-reduce:transition-none",
        "focus-visible:outline-none focus-visible:shadow-jp-focus",
        "disabled:cursor-not-allowed disabled:opacity-60",
        variantClasses[variant],
        fullWidth && "w-full",
        className,
      )}
      {...props}
    >
      {busy ? <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none" aria-hidden="true" /> : null}
      {children}
    </button>
  );
}

export const PrimaryButton = (props: Omit<ButtonProps, "variant">) => <Button variant="primary" {...props} />;
export const SecondaryButton = (props: Omit<ButtonProps, "variant">) => <Button variant="secondary" {...props} />;
