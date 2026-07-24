"use client";

import { useEffect, useId, useRef } from "react";
import { cn } from "@/lib/utils";
import { IconButton } from "@/components/ui/icon-button";

type Props = {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  children: React.ReactNode;
  closeAriaLabel?: string;
};

export function Drawer({ open, onClose, title, description, children, closeAriaLabel = "Close details" }: Props) {
  const titleId = useId();
  const descId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const onCloseRef = useRef(onClose);

  onCloseRef.current = onClose;

  useEffect(() => {
    if (!open) {
      return;
    }
    const prev = document.activeElement as HTMLElement | null;
    const focusTimer = window.setTimeout(() => {
      panelRef.current?.focus();
    }, 0);
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        e.stopPropagation();
        onCloseRef.current();
      }
    };
    document.addEventListener("keydown", onKey, true);
    document.body.style.overflow = "hidden";
    return () => {
      window.clearTimeout(focusTimer);
      document.removeEventListener("keydown", onKey, true);
      document.body.style.overflow = "";
      prev?.focus();
    };
  }, [open]);

  if (!open) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-[60] flex justify-end" role="presentation">
      <button
        type="button"
        className="absolute inset-0 bg-black/40 motion-reduce:transition-none"
        aria-label="Close drawer"
        onClick={onClose}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descId : undefined}
        tabIndex={-1}
        onKeyDown={(e) => {
          if (e.key === "Escape") {
            e.preventDefault();
            onClose();
          }
        }}
        className={cn(
          "relative flex h-full max-h-[100dvh] w-full max-w-lg flex-col border-l border-jp-border bg-white shadow-xl motion-reduce:transition-none sm:max-w-xl",
        )}
      >
        <header className="flex shrink-0 items-start gap-3 border-b border-jp-border px-4 py-4 sm:px-5">
          <div className="min-w-0 flex-1">
            <h2 id={titleId} className="text-lg font-semibold text-gray-900">
              {title}
            </h2>
            {description ? (
              <p id={descId} className="mt-1 text-sm text-jp-muted">
                {description}
              </p>
            ) : null}
          </div>
          <IconButton label={closeAriaLabel} onClick={onClose}>
            <span aria-hidden className="text-xl leading-none">
              ×
            </span>
          </IconButton>
        </header>
        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5">{children}</div>
      </div>
    </div>
  );
}
