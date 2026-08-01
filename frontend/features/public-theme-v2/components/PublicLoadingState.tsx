type PublicLoadingStateProps = {
  label?: string;
};

export function PublicLoadingState({ label = "Loading" }: PublicLoadingStateProps) {
  return (
    <div className="jp-v2-loading" role="status" aria-live="polite" aria-busy="true">
      <div className="jp-v2-loading__spinner" aria-hidden="true" />
      <p>{label}</p>
    </div>
  );
}
