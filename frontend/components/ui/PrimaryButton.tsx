import { cn } from "@/lib/cn";
import type { ButtonHTMLAttributes, ReactNode } from "react";

type PrimaryButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode;
  fullWidth?: boolean;
};

export function PrimaryButton({
  children,
  className,
  fullWidth = false,
  type = "button",
  ...props
}: PrimaryButtonProps) {
  return (
    <button
      type={type}
      className={cn(
        "inline-flex min-h-jp-button items-center justify-center rounded-jp-button px-jp-lg text-jp-sm font-semibold",
        "bg-jp-primary text-white shadow-jp-sm transition-colors duration-ui",
        "hover:bg-jp-primary-hover active:bg-jp-primary-active",
        "focus-visible:outline-none focus-visible:shadow-jp-focus",
        "disabled:cursor-not-allowed disabled:opacity-60",
        fullWidth && "w-full",
        className,
      )}
      {...props}
    >
      {children}
    </button>
  );
}
