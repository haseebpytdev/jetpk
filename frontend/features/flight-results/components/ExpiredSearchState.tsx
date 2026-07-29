type ExpiredSearchStateProps = {
  message: string;
  onNewSearch?: () => void;
};

export function ExpiredSearchState({ message, onNewSearch }: ExpiredSearchStateProps) {
  return (
    <div className="rounded-jp-card border border-amber-200 bg-amber-50 p-6" role="alert" data-testid="expired-search">
      <h2 className="text-lg font-semibold text-amber-900">Search expired</h2>
      <p className="mt-2 text-sm text-amber-800">{message}</p>
      {onNewSearch ? (
        <button
          type="button"
          className="mt-4 rounded-jp-md bg-jp-primary px-4 py-2 text-sm font-medium text-white hover:bg-jp-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
          onClick={onNewSearch}
        >
          Start a new search
        </button>
      ) : null}
    </div>
  );
}
