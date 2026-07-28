type EmptyContentStateProps = {
  title?: string;
  message?: string;
};

export function EmptyContentState({
  title = "No results found",
  message = "Try adjusting your search or browse all categories.",
}: EmptyContentStateProps) {
  return (
    <div className="rounded-jp-lg border border-dashed border-jp-border bg-jp-page p-jp-xl text-center" role="status">
      <p className="text-jp-md font-semibold text-jp-text">{title}</p>
      <p className="mt-2 text-jp-sm text-jp-muted">{message}</p>
    </div>
  );
}
