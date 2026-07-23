import { cn } from "@/lib/utils";
import type { ButtonHTMLAttributes } from "react";

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: "primary" | "secondary" | "ghost";
  size?: "sm" | "md" | "lg";
};

export function Button({
  className,
  variant = "primary",
  size = "md",
  type = "button",
  ...props
}: Props) {
  return (
    <button
      type={type}
      className={cn(
        "inline-flex min-h-11 items-center justify-center rounded-xl font-medium transition-colors duration-ui focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent disabled:cursor-not-allowed disabled:opacity-50",
        variant === "primary" && "bg-jp-accent text-white hover:bg-jp-accent-muted",
        variant === "secondary" && "border border-jp-border bg-white text-gray-900 hover:bg-gray-50",
        variant === "ghost" && "text-gray-700 hover:bg-gray-100",
        size === "sm" && "px-3 py-2 text-sm",
        size === "md" && "px-4 py-2.5 text-sm",
        size === "lg" && "px-5 py-3 text-base",
        className,
      )}
      {...props}
    />
  );
}
