"use client";

type SwitchToggleProps = {
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
  id?: string;
  label: string;
  description?: string;
  "data-testid"?: string;
};

/**
 * Accessible switch control for connection/channel enable state.
 */
export function SwitchToggle({
  checked,
  onChange,
  disabled = false,
  id,
  label,
  description,
  "data-testid": testId,
}: SwitchToggleProps) {
  return (
    <div className="flex items-start justify-between gap-3" data-testid={testId}>
      <div className="min-w-0">
        <label htmlFor={id} className="text-sm font-medium text-jp-ink">
          {label}
        </label>
        {description ? <p className="mt-0.5 text-xs text-jp-muted">{description}</p> : null}
      </div>
      <button
        id={id}
        type="button"
        role="switch"
        aria-checked={checked}
        aria-label={label}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={`relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition ${
          checked ? "border-jp-green bg-jp-green" : "border-jp-border bg-slate-200"
        } ${disabled ? "cursor-not-allowed opacity-50" : "cursor-pointer"}`}
      >
        <span
          className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${
            checked ? "translate-x-6" : "translate-x-1"
          }`}
        />
      </button>
    </div>
  );
}
