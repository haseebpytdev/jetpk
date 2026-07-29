export function ResultSkeleton({ count = 3 }: { count?: number }) {
  return (
    <div className="space-y-4" data-testid="result-skeleton" aria-busy="true" aria-label="Loading flight results">
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="animate-pulse rounded-jp-card border border-jp-border bg-jp-surface p-5 motion-reduce:animate-none"
        >
          <div className="flex gap-4">
            <div className="h-12 w-12 rounded-jp-sm bg-jp-border-soft" />
            <div className="flex-1 space-y-3">
              <div className="h-4 w-1/3 rounded bg-jp-border-soft" />
              <div className="h-8 w-full rounded bg-jp-border-soft" />
              <div className="h-3 w-1/2 rounded bg-jp-border-soft" />
            </div>
            <div className="h-10 w-24 rounded-jp-md bg-jp-border-soft" />
          </div>
        </div>
      ))}
    </div>
  );
}
