import { Skeleton } from "@/components/ui/skeleton";

export function LoadingState({
  label = "Loading",
  rows = 2,
}: {
  label?: string;
  rows?: number;
}) {
  return (
    <div aria-busy="true" aria-label={label} data-testid="shared-loading-state" className="space-y-4">
      {Array.from({ length: rows }).map((_, index) => (
        <Skeleton key={index} className={index === 0 ? "h-24 w-full" : "h-40 w-full"} />
      ))}
    </div>
  );
}
