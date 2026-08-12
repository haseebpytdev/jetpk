"use client";

import { cn } from "@/lib/cn";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";

type DropdownProps = {
  trigger: (props: {
    id: string;
    expanded: boolean;
    onToggle: () => void;
    onKeyDown: (event: React.KeyboardEvent<HTMLButtonElement>) => void;
    triggerRef: (node: HTMLButtonElement | null) => void;
  }) => ReactNode;
  children: ReactNode;
  align?: "start" | "end";
  placement?: "bottom" | "top";
  className?: string;
  panelClassName?: string;
  portal?: boolean;
  panelTestId?: string;
};

export function Dropdown({
  trigger,
  children,
  align = "start",
  placement = "bottom",
  className,
  panelClassName,
  portal = false,
  panelTestId,
}: DropdownProps) {
  const [open, setOpen] = useState(false);
  const [panelStyle, setPanelStyle] = useState<React.CSSProperties>({});
  const id = useId();
  const panelId = `${id}-panel`;
  const rootRef = useRef<HTMLDivElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement | null>(null);

  useEscapeKey(open, () => {
    setOpen(false);
    triggerRef.current?.focus();
  });

  useEffect(() => {
    if (!open) return;
    const handlePointerDown = (event: MouseEvent) => {
      const target = event.target as Node;
      if (rootRef.current?.contains(target) || panelRef.current?.contains(target)) return;
      setOpen(false);
    };
    document.addEventListener("mousedown", handlePointerDown);
    return () => document.removeEventListener("mousedown", handlePointerDown);
  }, [open]);

  useEffect(() => {
    if (!open || !portal) return;
    const updatePosition = () => updatePortalPosition();
    updatePosition();
    window.addEventListener("resize", updatePosition);
    window.addEventListener("scroll", updatePosition, true);
    return () => {
      window.removeEventListener("resize", updatePosition);
      window.removeEventListener("scroll", updatePosition, true);
    };
  }, [align, open, placement, portal]);

  const updatePortalPosition = () => {
    const rect = rootRef.current?.getBoundingClientRect();
    if (!rect) return;
    const panelHeight = panelRef.current?.offsetHeight ?? 0;
    const gap = 8;
    const viewportPad = 16;
    if (placement === "top") {
      setPanelStyle({
        position: "fixed",
        top: Math.max(viewportPad, rect.top - panelHeight - gap),
        left: align === "end" ? undefined : Math.max(viewportPad, rect.left),
        right: align === "end" ? Math.max(viewportPad, window.innerWidth - rect.right) : undefined,
        zIndex: 60,
        maxHeight: `min(16rem, ${Math.max(80, rect.top - viewportPad - gap)}px)`,
      });
      return;
    }
    setPanelStyle({
      position: "fixed",
      top: rect.bottom + gap,
      left: align === "end" ? undefined : Math.max(viewportPad, rect.left),
      right: align === "end" ? Math.max(viewportPad, window.innerWidth - rect.right) : undefined,
      zIndex: 60,
      maxHeight: `min(16rem, ${Math.max(80, window.innerHeight - rect.bottom - viewportPad - gap)}px)`,
    });
  };

  const handleToggle = () => {
    setOpen((value) => {
      const next = !value;
      if (next && portal) requestAnimationFrame(() => updatePortalPosition());
      return next;
    });
  };

  const openKey = placement === "top" ? "ArrowUp" : "ArrowDown";
  const handleKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === openKey || event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      setOpen(true);
      if (portal) requestAnimationFrame(() => updatePortalPosition());
    }
  };

  const panel = open ? (
    <div
      ref={panelRef}
      id={panelId}
      role="menu"
      data-testid={panelTestId}
      data-placement={placement}
      style={portal ? panelStyle : undefined}
      className={cn(
        "min-w-[12rem] overflow-y-auto rounded-jp-md border border-jp-border bg-jp-surface p-1.5 shadow-jp-md",
        portal
          ? undefined
          : cn(
              "absolute z-50",
              placement === "top" ? "bottom-full mb-2" : "top-full mt-2",
              align === "end" ? "right-0" : "left-0",
            ),
        !portal && placement === "top" && "max-h-[min(16rem,calc(100vh-6rem))]",
        panelClassName,
      )}
    >
      {children}
    </div>
  ) : null;

  return (
    <div ref={rootRef} className={cn("relative", className)}>
      {trigger({
        id: panelId,
        expanded: open,
        onToggle: handleToggle,
        onKeyDown: handleKeyDown,
        triggerRef: (node) => {
          triggerRef.current = node;
        },
      })}
      {portal && typeof document !== "undefined" ? createPortal(panel, document.body) : panel}
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
