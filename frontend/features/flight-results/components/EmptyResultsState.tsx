type EmptyResultsStateProps = {
  message: string;
  onNewSearch?: () => void;
};

export function EmptyResultsState({ message, onNewSearch }: EmptyResultsStateProps) {
  return (
    <div className="rounded-jp-card border border-jp-border bg-jp-surface p-8 text-center" data-testid="empty-results">
      <h2 className="text-lg font-semibold text-jp-text">No flights found</h2>
      <p className="mt-2 text-sm text-jp-text-muted">{message}</p>
      {onNewSearch ? (
        <button
          type="button"
          className="mt-4 rounded-jp-md bg-jp-primary px-4 py-2 text-sm font-medium text-white hover:bg-jp-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
          onClick={onNewSearch}
        >
          Modify search
        </button>
      ) : null}
    </div>
  );
}
