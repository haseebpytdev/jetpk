"use client";

import { cn } from "@/lib/cn";
import { currencyOptions } from "@/lib/navigation";
import type { CurrencyOption } from "@/types/navigation";
import { Dropdown, DropdownItem } from "@/components/ui/Dropdown";
import { useEffect, useState } from "react";

const STORAGE_KEY = "jp-currency-code";

function readStoredCurrency(): CurrencyOption {
  if (typeof window === "undefined") return currencyOptions[0];
  try {
    const code = window.localStorage.getItem(STORAGE_KEY);
    const match = currencyOptions.find((option) => option.code === code);
    return match ?? currencyOptions[0];
  } catch {
    return currencyOptions[0];
  }
}

type CurrencySelectorProps = {
  className?: string;
  appearance?: "default" | "footer";
};

export function CurrencySelector({ className, appearance = "default" }: CurrencySelectorProps) {
  const [currency, setCurrency] = useState<CurrencyOption>(currencyOptions[0]);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    setCurrency(readStoredCurrency());
    setHydrated(true);
  }, []);

  const select = (option: CurrencyOption) => {
    setCurrency(option);
    try {
      window.localStorage.setItem(STORAGE_KEY, option.code);
    } catch {
      // ignore
    }
  };

  const footer = appearance === "footer";

  return (
    <Dropdown
      className={className}
      align="end"
      placement={footer ? "top" : "bottom"}
      panelClassName={cn(
        footer
          ? "min-w-[13.5rem] max-w-[min(16rem,calc(100vw-2rem))] border-jp-border/80 bg-jp-surface p-1 shadow-jp-md"
          : "min-w-[12rem]",
      )}
      panelTestId={footer ? "currency-menu-panel" : undefined}
      trigger={({ id, expanded, onToggle, onKeyDown, triggerRef }) => (
        <button
          type="button"
          ref={triggerRef}
          aria-haspopup="menu"
          aria-expanded={expanded}
          aria-controls={id}
          aria-label={`Currency: ${currency.code}`}
          title={`Currency: ${currency.label}`}
          onClick={onToggle}
          onKeyDown={onKeyDown}
          data-testid="currency-selector"
          data-currency={currency.code}
          data-hydrated={hydrated ? "true" : "false"}
          className={cn(
            "inline-flex items-center transition-colors focus-visible:outline-none focus-visible:shadow-jp-focus",
            footer
              ? "min-h-9 gap-1.5 rounded-jp-md px-1.5 py-1 text-jp-sm text-white/85 hover:text-white"
              : "min-h-9 gap-1.5 rounded-jp-md border border-jp-border bg-jp-surface px-2 py-1.5 text-jp-sm font-medium text-jp-text hover:border-jp-primary hover:bg-jp-primary-soft",
          )}
        >
          {footer ? <span className="text-jp-xs font-medium uppercase tracking-wide text-white/60">Currency</span> : null}
          <span className={cn("font-semibold tracking-wide", footer ? "text-white" : undefined)}>{currency.code}</span>
          <ChevronDownIcon
            className={cn(
              "h-3.5 w-3.5 transition-transform",
              footer ? (expanded ? "rotate-0" : "rotate-180") : expanded && "rotate-180",
            )}
          />
        </button>
      )}
    >
      {currencyOptions.map((option) => {
        const selected = currency.code === option.code;
        return (
          <DropdownItem
            key={option.code}
            onSelect={() => select(option)}
            className={cn(
              "!px-2.5 !py-1.5 text-[12px] leading-5",
              selected
                ? "bg-jp-primary-soft font-semibold text-jp-text ring-1 ring-inset ring-jp-primary/25"
                : "font-normal",
            )}
          >
            <span className="flex w-full items-center gap-3 whitespace-nowrap">
              <span className="min-w-0 flex-1 truncate text-left">{option.label}</span>
              <span
                className={cn(
                  "shrink-0 text-right tabular-nums tracking-wide",
                  selected ? "font-semibold text-jp-primary" : "font-medium text-jp-muted",
                )}
              >
                {option.code}
              </span>
            </span>
          </DropdownItem>
        );
      })}
    </Dropdown>
  );
}

function ChevronDownIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}
