import { cn } from "@/lib/cn";
import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from "react";

const controlClass =
  "min-h-jp-control w-full rounded-jp-md border border-jp-border bg-jp-surface px-jp-control-padding-x text-jp-body text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus disabled:cursor-not-allowed disabled:opacity-60";

export function TextInput({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={cn(controlClass, className)} {...props} />;
}

export function Select({ className, children, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select className={cn(controlClass, className)} {...props}>
      {children}
    </select>
  );
}

export function Textarea({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea className={cn(controlClass, "py-jp-sm", className)} {...props} />;
}

export function FieldLabel({ children, htmlFor, required }: { children: ReactNode; htmlFor: string; required?: boolean }) {
  return (
    <label htmlFor={htmlFor} className="block text-jp-sm font-medium text-jp-text">
      {children}
      {required ? <span className="text-jp-danger"> *</span> : null}
    </label>
  );
}

export function FieldHelp({ children }: { children: ReactNode }) {
  return <p className="text-jp-xs text-jp-muted">{children}</p>;
}

export function FieldError({ children, id }: { children: ReactNode; id?: string }) {
  return (
    <p id={id} className="text-jp-xs text-jp-danger" role="alert">
      {children}
    </p>
  );
}

export function FormErrorSummary({ errors, title = "Please fix the following:" }: { errors: string[]; title?: string }) {
  if (errors.length === 0) return null;
  return (
    <div className="rounded-jp-md border border-jp-danger bg-jp-danger-soft p-jp-md" role="alert" data-testid="form-error-summary">
      <p className="text-jp-sm font-semibold text-jp-danger">{title}</p>
      <ul className="mt-2 list-disc space-y-1 pl-5 text-jp-sm text-jp-danger">
        {errors.map((error) => (
          <li key={error}>{error}</li>
        ))}
      </ul>
    </div>
  );
}

export function Checkbox({
  className,
  label,
  id,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode }) {
  const inputId = id ?? props.name;
  return (
    <label htmlFor={inputId} className="inline-flex min-h-jp-tap cursor-pointer items-center gap-2 text-jp-sm text-jp-text">
      <input
        type="checkbox"
        id={inputId}
        className={cn("h-4 w-4 rounded border-jp-border text-jp-brand focus-visible:shadow-jp-focus", className)}
        {...props}
      />
      <span>{label}</span>
    </label>
  );
}

export function Radio({ className, label, ...props }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode }) {
  return (
    <label className="inline-flex min-h-jp-tap cursor-pointer items-center gap-2 text-jp-sm text-jp-text">
      <input
        type="radio"
        className={cn("h-4 w-4 border-jp-border text-jp-brand focus-visible:shadow-jp-focus", className)}
        {...props}
      />
      <span>{label}</span>
    </label>
  );
}
