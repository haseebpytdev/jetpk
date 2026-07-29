type SearchProgressProps = {
  message: string;
};

export function SearchProgress({ message }: SearchProgressProps) {
  return (
    <div
      className="rounded-jp-md border border-jp-border bg-jp-surface-muted px-4 py-3 text-sm text-jp-text"
      role="status"
      aria-live="polite"
      data-testid="search-progress"
    >
      {message}
    </div>
  );
}
