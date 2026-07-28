"use client";

import { cn } from "@/lib/cn";
import { useId, useState } from "react";

type PasswordFieldProps = {
  id?: string;
  name?: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  autoComplete?: string;
  error?: string;
  required?: boolean;
  disabled?: boolean;
  hint?: string;
};

const fieldClass =
  "min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 pr-12 text-jp-sm text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

export function PasswordField({
  id,
  name = "password",
  label,
  value,
  onChange,
  autoComplete = "current-password",
  error,
  required = true,
  disabled = false,
  hint,
}: PasswordFieldProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-error`;
  const hintId = hint ? `${inputId}-hint` : undefined;
  const [visible, setVisible] = useState(false);

  return (
    <div>
      <label htmlFor={inputId} className="mb-1.5 block text-jp-sm font-medium text-jp-text">
        {label}
        {required ? <span className="text-jp-danger"> *</span> : null}
      </label>
      <div className="relative">
        <input
          id={inputId}
          name={name}
          type={visible ? "text" : "password"}
          autoComplete={autoComplete}
          required={required}
          disabled={disabled}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          aria-invalid={error ? true : undefined}
          aria-describedby={[hintId, error ? errorId : undefined].filter(Boolean).join(" ") || undefined}
          className={cn(fieldClass, error && "border-jp-danger")}
        />
        <button
          type="button"
          onClick={() => setVisible((current) => !current)}
          className="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-r-jp-md text-jp-xs font-semibold text-jp-muted hover:text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus"
          aria-label={visible ? "Hide password" : "Show password"}
        >
          {visible ? "Hide" : "Show"}
        </button>
      </div>
      {hint ? (
        <p id={hintId} className="mt-1 text-jp-xs text-jp-muted">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p id={errorId} role="alert" className="mt-1 text-jp-xs text-jp-danger">
          {error}
        </p>
      ) : null}
    </div>
  );
}
