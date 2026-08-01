import type { ReactNode } from "react";

type PublicCardProps = {
  children: ReactNode;
  interactive?: boolean;
  className?: string;
};

export function PublicCard({ children, interactive = false, className }: PublicCardProps) {
  return (
    <div
      className={[
        "jp-v2-card",
        interactive ? "jp-v2-card--interactive" : "",
        className,
      ]
        .filter(Boolean)
        .join(" ")}
    >
      {children}
    </div>
  );
}
