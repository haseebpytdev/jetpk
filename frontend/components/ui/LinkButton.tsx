import { cn } from "@/lib/cn";
import Link from "next/link";
import type { ComponentProps, ReactNode } from "react";

type LinkButtonProps = ComponentProps<typeof Link> & {
  children: ReactNode;
  variant?: "primary" | "secondary";
  external?: boolean;
};

export function LinkButton({
  children,
  className,
  variant = "primary",
  external,
  ...props
}: LinkButtonProps) {
  const classes = cn(
    "inline-flex min-h-jp-button items-center justify-center rounded-jp-button px-jp-lg text-jp-sm font-semibold",
    "transition-colors duration-jp-fast motion-reduce:transition-none",
    "focus-visible:outline-none focus-visible:shadow-jp-focus",
    variant === "primary"
      ? "bg-jp-brand text-white shadow-jp-sm hover:bg-jp-brand-hover"
      : "border border-jp-border bg-jp-surface text-jp-text hover:bg-jp-surface-muted",
    className,
  );

  if (external) {
    return (
      <a
        href={props.href as string}
        className={classes}
        target="_blank"
        rel="noopener noreferrer"
      >
        {children}
      </a>
    );
  }

  return (
    <Link className={classes} {...props}>
      {children}
    </Link>
  );
}
