"use client";

import { SearchModule } from "@/features/search";
import { cn } from "@/lib/cn";

type ModifySearchPanelProps = {
  open: boolean;
  onClose: () => void;
};

export function ModifySearchPanel({ open, onClose }: ModifySearchPanelProps) {
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-label="Modify search"
      data-testid="modify-search-panel"
    >
      <div className={cn("relative w-full max-w-4xl")}>
        <button
          type="button"
          className="absolute right-2 top-2 z-10 rounded-jp-md bg-jp-surface px-3 py-1 text-sm font-medium text-jp-text shadow-jp-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
          onClick={onClose}
        >
          Close
        </button>
        <SearchModule />
      </div>
    </div>
  );
}
