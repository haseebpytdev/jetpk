import { cn } from "@/lib/cn";

type SkeletonProps = {
  className?: string;
  rounded?: "sm" | "md" | "lg" | "pill";
};

const roundedMap = {
  sm: "rounded-jp-sm",
  md: "rounded-jp-md",
  lg: "rounded-jp-lg",
  pill: "rounded-jp-pill",
};

export function Skeleton({ className, rounded = "md" }: SkeletonProps) {
  return (
    <div
      className={cn("jp-skeleton-shimmer", roundedMap[rounded], className)}
      aria-hidden="true"
      data-testid="skeleton"
    />
  );
}

export function SkeletonText({ lines = 3, className }: { lines?: number; className?: string }) {
  return (
    <div className={cn("space-y-2", className)} aria-hidden="true">
      {Array.from({ length: lines }).map((_, index) => (
        <Skeleton
          key={index}
          className={cn("h-4", index === lines - 1 ? "w-3/4" : "w-full")}
        />
      ))}
    </div>
  );
}
