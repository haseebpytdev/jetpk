"use client";

type SupplierSourceBadgeProps = {
  label?: string | null;
};

/**
 * Privileged supplier-source chip. Only renders when the API includes a safe
 * display label (agent/admin). Guests/customers never receive the field.
 */
export function SupplierSourceBadge({ label }: SupplierSourceBadgeProps) {
  const text = (label ?? "").trim();
  if (!text) return null;

  return (
    <span
      className="inline-flex items-center rounded border border-jp-border-soft bg-jp-surface-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-jp-text-muted"
      data-testid="supplier-source-badge"
      title={`Supplier source: ${text}`}
    >
      {text}
    </span>
  );
}
