import type { InputHTMLAttributes } from "react";

type PublicTextFieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  hint?: string;
  error?: string;
  id: string;
};

export function PublicTextField({ label, hint, error, id, className, ...props }: PublicTextFieldProps) {
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

  return (
    <div className="jp-v2-field">
      <label htmlFor={id} className="jp-v2-field__label">
        {label}
      </label>
      <input
        id={id}
        className={["jp-v2-input", error ? "jp-v2-input--error" : "", className].filter(Boolean).join(" ")}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
        {...props}
      />
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
