type SearchErrorStateProps = {
  message: string;
  onRetry?: () => void;
};

export function SearchErrorState({ message, onRetry }: SearchErrorStateProps) {
  return (
    <div className="rounded-jp-card border border-red-200 bg-red-50 p-6" role="alert" data-testid="search-error">
      <h2 className="text-lg font-semibold text-red-900">Unable to load results</h2>
      <p className="mt-2 text-sm text-red-800">{message}</p>
      {onRetry ? (
        <button
          type="button"
          className="mt-4 rounded-jp-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-900 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
          onClick={onRetry}
        >
          Try again
        </button>
      ) : null}
    </div>
  );
}
