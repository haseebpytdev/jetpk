"use client";

import { cn } from "@/lib/cn";
import { useBodyScrollLock } from "@/lib/hooks/use-body-scroll-lock";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useFocusTrap } from "@/lib/hooks/use-focus-trap";
import { type ReactNode, useId, useRef } from "react";

type DrawerProps = {
  open: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
  side?: "right" | "left";
  className?: string;
};

export function Drawer({
  open,
  onClose,
  title,
  children,
  side = "right",
  className,
}: DrawerProps) {
  const panelRef = useRef<HTMLDivElement>(null);
  const titleId = useId();

  useBodyScrollLock(open);
  useFocusTrap(open, panelRef);
  useEscapeKey(open, onClose);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[100]" role="presentation">
      <button
        type="button"
        className="jp-overlay-backdrop absolute inset-0 bg-black/40"
        aria-label="Close drawer"
        onClick={onClose}
        tabIndex={-1}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={cn(
          "jp-drawer-panel absolute top-0 flex h-full w-full max-w-sm flex-col border-jp-border bg-jp-surface shadow-jp-lg",
          side === "right" ? "right-0 border-l" : "left-0 border-r",
          className,
        )}
        data-testid="drawer-panel"
      >
        <div className="flex items-center justify-between border-b border-jp-border px-4 py-3">
          <h2 id={titleId} className="text-base font-semibold text-jp-text">
            {title}
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-jp-md p-1 text-jp-muted hover:text-jp-text focus-visible:shadow-jp-focus"
            aria-label="Close"
          >
            <svg viewBox="0 0 20 20" className="h-5 w-5" fill="currentColor" aria-hidden="true">
              <path d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414z" />
            </svg>
          </button>
        </div>
        <div className="flex-1 overflow-y-auto p-4">{children}</div>
      </div>
    </div>
  );
}
