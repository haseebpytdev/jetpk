import { cn } from "@/lib/utils";

export function Skeleton({ className }: { className?: string }) {
  return (
    <div
      className={cn("animate-pulse rounded-lg bg-gray-200/80 motion-reduce:animate-none", className)}
      aria-hidden
    />
  );
}
