"use client";

import { SORT_CONTROLS, type UiSortKey } from "../utils/sorting";

type SortControlProps = {
  value: UiSortKey;
  onChange: (value: UiSortKey) => void;
};

export function SortControl({ value, onChange }: SortControlProps) {
  return (
    <label className="flex items-center gap-2 text-sm text-jp-text">
      <span className="sr-only">Sort results by</span>
      <span aria-hidden="true" className="text-jp-text-muted">
        Sort
      </span>
      <select
        className="rounded-jp-md border border-jp-border bg-jp-surface px-2 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
        value={value}
        onChange={(event) => onChange(event.target.value as UiSortKey)}
        data-testid="sort-control"
      >
        {SORT_CONTROLS.map((option) => (
          <option key={option.key} value={option.key}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}
