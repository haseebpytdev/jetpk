"use client";

import { cn } from "@/lib/cn";

type SearchFormErrorsProps = {
  errors: string[];
  className?: string;
};

export function SearchFormErrors({ errors, className }: SearchFormErrorsProps) {
  if (errors.length === 0) return null;

  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        "rounded-jp-md border border-red-200 bg-red-50 px-3 py-2 text-jp-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200",
        className,
      )}
    >
      <ul className="list-disc pl-4">
        {errors.map((error) => (
          <li key={error}>{error}</li>
        ))}
      </ul>
    </div>
  );
}

export type SearchLayout = "default" | "compact";
