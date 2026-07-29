import { cn } from "@/lib/cn";
import type { HTMLAttributes, ReactNode } from "react";

type SurfaceProps = HTMLAttributes<HTMLDivElement> & {
  children: ReactNode;
  elevated?: boolean;
  muted?: boolean;
  padding?: "none" | "sm" | "md" | "lg";
};

const paddingMap = {
  none: "",
  sm: "p-jp-md",
  md: "p-jp-lg",
  lg: "p-jp-xl",
};

export function Surface({
  children,
  className,
  elevated = false,
  muted = false,
  padding = "md",
  ...props
}: SurfaceProps) {
  return (
    <div
      className={cn(
        "rounded-jp-card border border-jp-border",
        muted ? "bg-jp-surface-muted" : "bg-jp-surface",
        elevated && "shadow-jp-card",
        paddingMap[padding],
        className,
      )}
      {...props}
    >
      {children}
    </div>
  );
}

export function Card(props: SurfaceProps) {
  return <Surface elevated {...props} />;
}

export function Divider({ className }: { className?: string }) {
  return <hr className={cn("border-0 border-t border-jp-border", className)} aria-hidden="true" />;
}
