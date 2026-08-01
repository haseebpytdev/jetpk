import type { ReactNode } from "react";
import { PublicButton } from "./PublicButton";

type Tone = "info" | "success" | "warning" | "danger";

type PublicCalloutProps = {
  tone?: Tone;
  heading?: string;
  body: ReactNode;
  actionLabel?: string;
  onAction?: () => void;
};

export function PublicCallout({ tone = "info", heading, body, actionLabel, onAction }: PublicCalloutProps) {
  return (
    <aside className={["jp-v2-callout", `jp-v2-callout--${tone}`].join(" ")} role="note">
      {heading ? <p className="jp-v2-callout__title">{heading}</p> : null}
      <div>{body}</div>
      {actionLabel && onAction ? (
        <p style={{ marginTop: "var(--jp-v2-space-md)" }}>
          <PublicButton variant="secondary" onClick={onAction}>
            {actionLabel}
          </PublicButton>
        </p>
      ) : null}
    </aside>
  );
}
