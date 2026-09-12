import { cn } from "@/lib/cn";
import {
  UI_ICON_ACTION_CLASS,
  UI_ICON_COMPACT_CLASS,
  UI_ICON_STROKE,
} from "@/lib/ui-icon";
import { ChevronDown, X, type LucideProps } from "lucide-react";

type SharedIconProps = Omit<LucideProps, "strokeWidth"> & {
  className?: string;
};

/** Chevron for menus, dropdown triggers, and nav expanders. */
export function UiChevronDownIcon({ className, ...props }: SharedIconProps) {
  return (
    <ChevronDown
      className={cn(UI_ICON_ACTION_CLASS, className)}
      strokeWidth={UI_ICON_STROKE}
      aria-hidden="true"
      {...props}
    />
  );
}

/** Chevron aligned to compact form controls (native select affordance). */
export function UiSelectChevronIcon({ className, ...props }: SharedIconProps) {
  return (
    <ChevronDown
      className={cn(UI_ICON_COMPACT_CLASS, className)}
      strokeWidth={UI_ICON_STROKE}
      aria-hidden="true"
      {...props}
    />
  );
}

/** Close affordance for dialogs, drawers, and dismiss controls. */
export function UiCloseIcon({ className, ...props }: SharedIconProps) {
  return (
    <X
      className={cn(UI_ICON_ACTION_CLASS, className)}
      strokeWidth={UI_ICON_STROKE}
      aria-hidden="true"
      {...props}
    />
  );
}
