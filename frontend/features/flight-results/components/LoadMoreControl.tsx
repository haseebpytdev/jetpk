type LoadMoreControlProps = {
  hasMore: boolean;
  loading: boolean;
  onLoadMore: () => void;
  total: number;
  shown: number;
};

export function LoadMoreControl({ hasMore, loading, onLoadMore, total, shown }: LoadMoreControlProps) {
  if (!hasMore) return null;
  return (
    <div className="flex flex-col items-center gap-2 py-4">
      <p className="text-sm text-jp-text-muted">
        Showing {shown} of {total} results
      </p>
      <button
        type="button"
        className="rounded-jp-md border border-jp-border bg-jp-surface px-4 py-2 text-sm font-medium text-jp-text hover:bg-jp-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary disabled:opacity-60"
        onClick={onLoadMore}
        disabled={loading}
        aria-busy={loading}
      >
        {loading ? "Loading…" : "Load more"}
      </button>
    </div>
  );
}
