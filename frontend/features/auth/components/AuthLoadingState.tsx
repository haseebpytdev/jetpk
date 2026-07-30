import { Skeleton } from "@/components/ui/Skeleton";

export function AuthLoadingState() {
  return (
    <div className="space-y-4" data-testid="auth-loading-state" aria-busy="true" aria-label="Loading">
      <Skeleton className="h-8 w-2/3" />
      <Skeleton className="h-4 w-full" />
      <Skeleton className="h-11 w-full" />
      <Skeleton className="h-11 w-full" />
      <Skeleton className="h-11 w-full" />
    </div>
  );
}
