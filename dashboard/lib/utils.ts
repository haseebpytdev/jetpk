import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function stableErrorId(prefix: string): string {
  return `${prefix}-${Date.now().toString(36)}`;
}
