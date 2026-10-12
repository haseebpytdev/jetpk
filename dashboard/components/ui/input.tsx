"use client";

import { cn } from "@/lib/utils";
import { useEffect, useState, type InputHTMLAttributes } from "react";

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      className={cn(
        "min-h-11 w-full rounded-xl border border-jp-border bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500",
        className,
      )}
      {...props}
    />
  );
}

export function SearchInput({
  className,
  onClear,
  value,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & { onClear?: () => void }) {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const showClear =
    mounted && onClear != null && String(value ?? "").length > 0;

  return (
    <div className={cn("relative", className)}>
      <Input type="search" value={value} className={showClear ? "pr-10" : undefined} {...props} />
      {showClear ? (
        <button
          type="button"
          className="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
          onClick={onClear}
          aria-label="Clear search"
        >
          ×
        </button>
      ) : null}
    </div>
  );
}

export function DateInput(props: InputHTMLAttributes<HTMLInputElement>) {
  return <Input type="date" {...props} />;
}
