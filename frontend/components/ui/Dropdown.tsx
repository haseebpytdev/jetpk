"use client";

import { cn } from "@/lib/cn";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useEffect, useId, useRef, useState, type ReactNode } from "react";

type DropdownProps = {
  trigger: (props: {
    id: string;
    expanded: boolean;
    onToggle: () => void;
    onKeyDown: (event: React.KeyboardEvent<HTMLButtonElement>) => void;
  }) => ReactNode;
  children: ReactNode;
  align?: "start" | "end";
  className?: string;
  panelClassName?: string;
};

export function Dropdown({
  trigger,
  children,
  align = "start",
  className,
  panelClassName,
}: DropdownProps) {
  const [open, setOpen] = useState(false);
  const id = useId();
  const panelId = `${id}-panel`;
  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement | null>(null);

  useEscapeKey(open, () => {
    setOpen(false);
    triggerRef.current?.focus();
  });

  useEffect(() => {
    if (!open) return;

    const handlePointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    document.addEventListener("mousedown", handlePointerDown);
    return () => document.removeEventListener("mousedown", handlePointerDown);
  }, [open]);

  const handleToggle = () => setOpen((value) => !value);

  const handleKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      setOpen(true);
    }
  };

  return (
    <div ref={rootRef} className={cn("relative", className)}>
      {trigger({
        id: panelId,
        expanded: open,
        onToggle: handleToggle,
        onKeyDown: handleKeyDown,
      })}
      {open ? (
        <div
          id={panelId}
          role="menu"
          className={cn(
            "absolute z-50 mt-2 min-w-[12rem] rounded-jp-md border border-jp-border bg-jp-surface p-2 shadow-jp-md",
            align === "end" ? "right-0" : "left-0",
            panelClassName,
          )}
        >
          {children}
        </div>
      ) : null}
    </div>
  );
}

export function DropdownItem({
  children,
  className,
  onSelect,
}: {
  children: ReactNode;
  className?: string;
  onSelect?: () => void;
}) {
  return (
    <button
      type="button"
      role="menuitem"
      className={cn(
        "flex w-full rounded-jp-sm px-3 py-2 text-left text-jp-sm text-jp-text transition-colors hover:bg-jp-primary-soft",
        "focus-visible:outline-none focus-visible:shadow-jp-focus",
        className,
      )}
      onClick={onSelect}
    >
      {children}
    </button>
  );
}

export function DropdownLinkItem({
  href,
  children,
  description,
  onNavigate,
}: {
  href: string;
  children: ReactNode;
  description?: string;
  onNavigate?: () => void;
}) {
  return (
    <a
      href={href}
      role="menuitem"
      className="block rounded-jp-sm px-3 py-2 text-jp-sm text-jp-text transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
      onClick={onNavigate}
    >
      <span className="block font-medium">{children}</span>
      {description ? <span className="mt-0.5 block text-jp-xs text-jp-muted">{description}</span> : null}
    </a>
  );
}
