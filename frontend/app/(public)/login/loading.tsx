/** Soft-nav: show shell immediately while login RSC/client hydrate. */
export default function LoginLoading() {
  return (
    <div className="mx-auto flex min-h-[24rem] w-full max-w-jp-container items-center justify-center px-jp-xl py-jp-4xl">
      <div className="h-48 w-full max-w-md animate-pulse rounded-jp-card border border-jp-border bg-jp-surface-muted" />
    </div>
  );
}
