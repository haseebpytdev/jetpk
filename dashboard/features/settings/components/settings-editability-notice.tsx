"use client";

import type { ReactNode } from "react";

type EditabilityStatus =
  | "EDITABLE"
  | "READ_ONLY_BY_DESIGN"
  | "OWNER_INPUT_REQUIRED"
  | "EXTERNAL_MANAGEMENT"
  | "NOT_APPLICABLE";

const STATUS_COPY: Record<EditabilityStatus, string> = {
  EDITABLE: "Editable through the linked live control below.",
  READ_ONLY_BY_DESIGN: "Read-only by current platform policy.",
  OWNER_INPUT_REQUIRED: "Owner input required outside the dashboard.",
  EXTERNAL_MANAGEMENT: "Managed in the linked external module.",
  NOT_APPLICABLE: "Not persisted in the current Laravel domain.",
};

type Props = {
  title: string;
  status: EditabilityStatus;
  detail?: string;
  children?: ReactNode;
};

export function SettingsEditabilityNotice({ title, status, detail, children }: Props) {
  return (
    <section
      className="rounded-xl border border-jp-border bg-white p-4"
      data-testid="settings-editability-notice"
      data-editability={status}
    >
      <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
      <p className="mt-1 text-sm text-jp-muted">{detail ?? STATUS_COPY[status]}</p>
      {children ? <div className="mt-3">{children}</div> : null}
    </section>
  );
}
