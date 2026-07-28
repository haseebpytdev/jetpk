"use client";

import { Dropdown, DropdownItem } from "@/components/ui/Dropdown";
import { currencyOptions } from "@/lib/navigation";
import { cn } from "@/lib/cn";
import { useState } from "react";

type CurrencySelectorProps = {
  className?: string;
};

export function CurrencySelector({ className }: CurrencySelectorProps) {
  const [currency, setCurrency] = useState(currencyOptions[0]);

  return (
    <Dropdown
      className={className}
      align="end"
      panelClassName="min-w-[14rem]"
      trigger={({ id, expanded, onToggle, onKeyDown }) => (
        <button
          type="button"
          aria-haspopup="menu"
          aria-expanded={expanded}
          aria-controls={id}
          onClick={onToggle}
          onKeyDown={onKeyDown}
          className={cn(
            "inline-flex min-h-jp-button items-center gap-2 rounded-jp-md border border-jp-border bg-jp-surface px-3 text-jp-sm font-medium text-jp-text",
            "transition-colors hover:border-jp-primary hover:bg-jp-primary-soft",
            "focus-visible:outline-none focus-visible:shadow-jp-focus",
          )}
        >
          <span aria-hidden="true" className="text-base leading-none">
            🇵🇰
          </span>
          <span>{currency.code}</span>
          <ChevronDownIcon className={cn("h-4 w-4 transition-transform", expanded && "rotate-180")} />
        </button>
      )}
    >
      {currencyOptions.map((option) => (
        <DropdownItem
          key={option.code}
          onSelect={() => setCurrency(option)}
          className={currency.code === option.code ? "bg-jp-primary-soft font-semibold" : undefined}
        >
          <span className="flex items-center justify-between gap-3">
            <span>{option.label}</span>
            <span className="text-jp-muted">{option.code}</span>
          </span>
        </DropdownItem>
      ))}
    </Dropdown>
  );
}

function ChevronDownIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path
        d="M5 7.5L10 12.5L15 7.5"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
