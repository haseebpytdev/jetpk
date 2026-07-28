import { cn } from "@/lib/cn";
import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from "react";

type IconButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode;
  label: string;
  size?: "sm" | "md";
};

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(function IconButton(
  {
    children,
    label,
    className,
    size = "md",
    type = "button",
    ...props
  },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      aria-label={label}
      className={cn(
        "inline-flex items-center justify-center rounded-jp-md border border-jp-border bg-jp-surface text-jp-text",
        "transition-colors duration-ui hover:border-jp-primary hover:bg-jp-primary-soft",
        "focus-visible:outline-none focus-visible:shadow-jp-focus",
        size === "sm" ? "h-10 w-10" : "h-11 w-11",
        className,
      )}
      {...props}
    >
      {children}
    </button>
  );
});
