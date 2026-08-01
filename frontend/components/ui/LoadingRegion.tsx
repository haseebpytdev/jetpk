import { Skeleton, SkeletonText } from "@/components/ui/Skeleton";

type LoadingRegionProps = {
  label: string;
  children?: React.ReactNode;
  busy?: boolean;
};

export function LoadingRegion({ label, children, busy = true }: LoadingRegionProps) {
  return (
    <div aria-busy={busy} aria-label={label} role="status">
      {children}
    </div>
  );
}

export function RouteLoadingSkeleton({ label }: { label: string }) {
  return (
    <LoadingRegion label={label}>
      <div className="mx-auto max-w-jp-booking space-y-4 p-4">
        <Skeleton className="h-8 w-1/3" />
        <SkeletonText lines={4} />
        <Skeleton className="h-40 w-full" />
      </div>
    </LoadingRegion>
  );
}

export function TableLoadingSkeleton({ rows = 6, label = "Loading table" }: { rows?: number; label?: string }) {
  return (
    <LoadingRegion label={label}>
      <div className="space-y-2">
        <Skeleton className="h-10 w-full" />
        {Array.from({ length: rows }).map((_, index) => (
          <Skeleton key={index} className="h-12 w-full" />
        ))}
      </div>
    </LoadingRegion>
  );
}

export function CardListLoadingSkeleton({ count = 4, label = "Loading list" }: { count?: number; label?: string }) {
  return (
    <LoadingRegion label={label}>
      <div className="space-y-3">
        {Array.from({ length: count }).map((_, index) => (
          <Skeleton key={index} className="h-28 w-full rounded-jp-lg" />
        ))}
      </div>
    </LoadingRegion>
  );
}
