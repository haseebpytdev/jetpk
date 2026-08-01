import type { ReactNode } from "react";

type Tone = "info" | "success" | "warning" | "danger";

type PublicAlertProps = {
  tone?: Tone;
  title: string;
  children: ReactNode;
};

export function PublicAlert({ tone = "info", title, children }: PublicAlertProps) {
  return (
    <div className={["jp-v2-alert", `jp-v2-alert--${tone}`].join(" ")} role="status">
      <p className="jp-v2-alert__title">{title}</p>
      <div>{children}</div>
    </div>
  );
}
