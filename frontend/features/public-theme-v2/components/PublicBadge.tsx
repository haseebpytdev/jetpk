import type { ReactNode } from "react";

type Tone = "default" | "success" | "warning" | "error" | "info";

type PublicBadgeProps = {
  children: ReactNode;
  tone?: Tone;
};

const toneClass: Record<Tone, string> = {
  default: "",
  success: "jp-v2-badge--success",
  warning: "jp-v2-badge--warning",
  error: "jp-v2-badge--error",
  info: "jp-v2-badge--info",
};

export function PublicBadge({ children, tone = "default" }: PublicBadgeProps) {
  return (
    <span className={["jp-v2-badge", toneClass[tone]].filter(Boolean).join(" ")}>
      {children}
    </span>
  );
}
