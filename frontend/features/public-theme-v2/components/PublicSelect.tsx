import type { SelectHTMLAttributes, ReactNode } from "react";

type PublicSelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label: string;
  hint?: string;
  error?: string;
  id: string;
  children: ReactNode;
};

export function PublicSelect({ label, hint, error, id, children, className, ...props }: PublicSelectProps) {
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

  return (
    <div className="jp-v2-field">
      <label htmlFor={id} className="jp-v2-field__label">
        {label}
      </label>
      <select
        id={id}
        className={["jp-v2-select", error ? "jp-v2-input--error" : "", className].filter(Boolean).join(" ")}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
        {...props}
      >
        {children}
      </select>
      {hint ? (
        <span id={hintId} className="jp-v2-field__hint">
          {hint}
        </span>
      ) : null}
      {error ? (
        <span id={errorId} className="jp-v2-field__error" role="alert">
          {error}
        </span>
      ) : null}
    </div>
  );
}
