import { cn } from "@/lib/utils";
import type { InputHTMLAttributes } from "react";

export function Checkbox({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      type="checkbox"
      className={cn(
        "h-4 w-4 rounded border-jp-border text-jp-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent",
        className,
      )}
      {...props}
    />
  );
}
